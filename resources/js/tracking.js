const config = window.trackingConfig
const root = document.getElementById('tracking-page')

if (config && root) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    const selectedPlaceIcon = L.icon({
        iconUrl: '/images/lokasi2.png',
        iconSize: [28, 36],
        iconAnchor: [14, 34],
    })
    const savedPlaceIcon = L.icon({
        iconUrl: '/images/buildinglokasi.png',
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    })
    const destinationIcons = {
        pin: '📍',
        office: '🏢',
        warehouse: '🏭',
        store: '🏪',
        meeting: '🤝',
        target: '🎯',
    }
    const destinationGlyph = (icon) => destinationIcons[icon] ?? destinationIcons.pin

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character])

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.method === 'POST' ? { 'X-CSRF-TOKEN': csrf } : {}),
                ...options.headers,
            },
        })
        const result = await response.json().catch(() => ({}))

        if (!response.ok) {
            const validationError = Object.values(result.errors ?? {}).flat()[0]
            throw new Error(validationError ?? result.message ?? `Permintaan gagal (${response.status}).`)
        }

        return result
    }

    const LocationGeo = {
        distance(a, b) {
            const radians = (value) => value * Math.PI / 180
            const latitudeDelta = radians(b[0] - a[0])
            const longitudeDelta = radians(b[1] - a[1])
            const h = Math.sin(latitudeDelta / 2) ** 2
                + Math.cos(radians(a[0])) * Math.cos(radians(b[0])) * Math.sin(longitudeDelta / 2) ** 2

            return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h))
        },
        bearing(a, b) {
            const radians = (value) => value * Math.PI / 180
            const y = Math.sin(radians(b[1] - a[1])) * Math.cos(radians(b[0]))
            const x = Math.cos(radians(a[0])) * Math.sin(radians(b[0]))
                - Math.sin(radians(a[0])) * Math.cos(radians(b[0])) * Math.cos(radians(b[1] - a[1]))

            return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360
        },
        cumulativeDistances(line) {
            return line.reduce((distances, point, index) => {
                distances.push(index === 0 ? 0 : distances[index - 1] + this.distance(line[index - 1], point))
                return distances
            }, [])
        },
        interpolate(line, distanceMeters) {
            const distances = this.cumulativeDistances(line)
            if (line.length === 0) return null
            if (distanceMeters <= 0) return line[0]
            if (distanceMeters >= distances.at(-1)) return line.at(-1)

            const index = distances.findIndex((distance) => distance >= distanceMeters)
            const ratio = (distanceMeters - distances[index - 1]) / (distances[index] - distances[index - 1])

            return [
                line[index - 1][0] + (line[index][0] - line[index - 1][0]) * ratio,
                line[index - 1][1] + (line[index][1] - line[index - 1][1]) * ratio,
            ]
        },
        projectOnLine(point, line) {
            if (line.length < 2) return null
            const scale = Math.cos(point[0] * Math.PI / 180)
            let closest = { point: line[0], distance: Infinity, along: 0 }
            const cumulative = this.cumulativeDistances(line)

            for (let index = 1; index < line.length; index += 1) {
                const start = line[index - 1]
                const end = line[index]
                const x = (end[1] - start[1]) * scale
                const y = end[0] - start[0]
                const px = (point[1] - start[1]) * scale
                const py = point[0] - start[0]
                const ratio = Math.max(0, Math.min(1, (px * x + py * y) / (x * x + y * y || 1)))
                const projected = [start[0] + (end[0] - start[0]) * ratio, start[1] + (end[1] - start[1]) * ratio]
                const distance = this.distance(point, projected)

                if (distance < closest.distance) {
                    closest = {
                        point: projected,
                        distance,
                        along: cumulative[index - 1] + (cumulative[index] - cumulative[index - 1]) * ratio,
                    }
                }
            }

            return closest
        },
    }

    const GeocodingAPI = {
        async search(query) {
            const params = new URLSearchParams({ q: query })
            return request(`${config.routes.search}?${params}`)
        },
        async reverse(coordinate) {
            const params = new URLSearchParams({ latitude: coordinate[0], longitude: coordinate[1] })
            return request(`${config.routes.reverse}?${params}`)
        },
    }

    const RoutingAPI = {
        async getRoute(waypoints) {
            const params = new URLSearchParams({
                from_latitude: waypoints[0][0],
                from_longitude: waypoints[0][1],
                to_latitude: waypoints[1][0],
                to_longitude: waypoints[1][1],
            })
            return request(`${config.routes.route}?${params}`)
        },
    }

    const MapController = {
        instance: null,
        baseLayer: null,
        routeLayer: null,
        historyLayer: null,
        journeyLayer: null,
        journeyMapInstance: null,
        journeyMapBaseLayer: null,
        journeyMapRouteLayer: null,
        journeyMapLayer: null,
        markerLayer: null,
        destinationLayer: null,
        annotationLayer: null,
        vehicleMarker: null,
        currentJourneyMarker: null,
        clickHandler: null,
        markerSelectHandler: null,
        journeyPointHandler: null,
        destinationSelectHandler: null,
        initialize(container, options = {}) {
            this.destroy()
            this.instance = L.map(container, {
                zoomControl: true,
                scrollWheelZoom: true,
                wheelPxPerZoomLevel: 60,
                zoomSnap: 0.5,
                zoomDelta: 0.5,
                doubleClickZoom: false,
                ...options,
            })
            this.setBaseLayer('osm')
            this.routeLayer = L.polyline([], { color: '#2563eb', weight: 5, opacity: 0.9 }).addTo(this.instance)
            this.historyLayer = L.polyline([], { color: '#2563eb', weight: 4, opacity: 0.75 }).addTo(this.instance)
            this.journeyLayer = L.layerGroup().addTo(this.instance)
            this.markerLayer = L.layerGroup().addTo(this.instance)
            this.destinationLayer = L.layerGroup().addTo(this.instance)
            this.annotationLayer = L.layerGroup().addTo(this.instance)
            this.instance.on('click', (event) => this.clickHandler?.([event.latlng.lat, event.latlng.lng]))
            this.instance.setView(options.center ?? [-7.4243, 109.2302], options.zoom ?? 14)

            return this.instance
        },
        destroy() {
            this.instance?.remove()
            this.instance = null
            this.journeyMapInstance?.remove()
            this.journeyMapInstance = null
        },
        initializeJourneyMap(container) {
            if (this.journeyMapInstance) return this.journeyMapInstance
            this.journeyMapInstance = L.map(container, {
                zoomControl: true,
                scrollWheelZoom: true,
                wheelPxPerZoomLevel: 60,
                zoomSnap: 0.5,
                zoomDelta: 0.5,
            }).setView([-7.4243, 109.2302], 13)
            this.journeyMapBaseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.journeyMapInstance)
            this.journeyMapRouteLayer = L.polyline([], {
                color: '#2563eb',
                weight: 5,
                opacity: 0.85,
                lineCap: 'round',
                lineJoin: 'round',
            }).addTo(this.journeyMapInstance)
            this.journeyMapLayer = L.layerGroup().addTo(this.journeyMapInstance)

            return this.journeyMapInstance
        },
        invalidateJourneyMap() {
            this.journeyMapInstance?.invalidateSize({ pan: false })
        },
        fitJourneyRoute() {
            if (!this.journeyMapInstance) return
            const bounds = L.featureGroup([this.journeyMapRouteLayer, this.journeyMapLayer]).getBounds()
            if (bounds.isValid()) this.journeyMapInstance.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 })
        },
        panToJourney(point) {
            this.journeyMapInstance?.panTo(point, { animate: false })
        },
        setBaseLayer(layer) {
            if (!this.instance) return
            if (this.baseLayer) this.instance.removeLayer(this.baseLayer)
            this.baseLayer = L.tileLayer(layer === 'osm'
                ? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
                : layer, {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.instance)
        },
        setRoute(route, options = {}) {
            const layer = options.history ? this.historyLayer : this.routeLayer
            if (!layer) return
            const points = Array.isArray(route) && route.length && typeof route[0][0] === 'number'
                ? route.map((point) => options.geoJson ? [point[1], point[0]] : point)
                : route?.coordinates?.map((point) => [point[1], point[0]]) ?? []
            layer.setLatLngs(points)
        },
        setJourney(points, visits, realtimePoint = null, destinations = []) {
            if (!this.journeyMapLayer || !this.journeyMapRouteLayer) return
            this.journeyMapLayer.clearLayers()
            this.currentJourneyMarker = null
            this.journeyMapRouteLayer.setLatLngs(points.map((point) => [point.latitude, point.longitude]))
            if (points.length) {
                const start = points[0]
                const startMarker = L.circleMarker([start.latitude, start.longitude], {
                    radius: 8, color: '#fff', weight: 2, fillColor: '#16a34a', fillOpacity: 1,
                }).addTo(this.journeyMapLayer)
                startMarker.bindPopup(`<strong>Mulai</strong><br>${escapeHtml(start.recordedAt ? formatTime(start.recordedAt) : start.time)}`)
                startMarker.on('click', () => this.journeyPointHandler?.({ type: 'start', point: start }))

                visits.forEach((visit, index) => {
                    const marker = L.marker([visit.latitude, visit.longitude], {
                        icon: L.divIcon({
                            className: '',
                            html: `<span class="grid size-7 place-items-center rounded-full border-2 border-white bg-amber-500 text-[10px] font-semibold text-white shadow">${index + 1}</span>`,
                            iconSize: [28, 28],
                            iconAnchor: [14, 14],
                        }),
                    }).addTo(this.journeyMapLayer)
                    marker.bindPopup(`<strong>${escapeHtml(visit.name)}</strong><br>${formatTime(visit.arrivedAt)} · ${formatDuration(visit.durationMs)}`)
                    marker.on('click', () => this.journeyPointHandler?.({ type: 'visit', visit, index }))
                })

                if (points.length > 1) {
                    const last = points.at(-1)
                    const endMarker = L.circleMarker([last.latitude, last.longitude], {
                        radius: 8, color: '#fff', weight: 2, fillColor: '#dc2626', fillOpacity: 1,
                    }).addTo(this.journeyMapLayer)
                    endMarker.bindPopup(`<strong>Terakhir tercatat</strong><br>${formatTime(last.recordedAt)}`)
                    endMarker.on('click', () => this.journeyPointHandler?.({ type: 'end', point: last }))
                }
            }

            destinations.forEach((destination) => {
                const marker = L.marker([destination.latitude, destination.longitude], {
                    icon: L.divIcon({
                        className: '',
                        html: `<span class="grid size-8 place-items-center rounded-full border-2 border-white bg-indigo-600 text-base shadow">${destinationGlyph(destination.icon)}</span>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                    }),
                }).addTo(this.journeyMapLayer)
                marker.bindTooltip(escapeHtml(destination.name), {
                    permanent: true,
                    direction: 'right',
                    offset: [8, 0],
                    className: 'destination-tooltip',
                })
                const visitText = destination.visited
                    ? `Dikunjungi ${formatTime(destination.visitedAt)}`
                    : `Belum dikunjungi · radius ${destination.geofenceRadiusMeters} m`
                marker.bindPopup(`<strong>${destinationGlyph(destination.icon)} ${escapeHtml(destination.name)}</strong><br>${escapeHtml(visitText)}${destination.description ? `<br>${escapeHtml(destination.description)}` : ''}`)
                marker.on('click', () => this.journeyPointHandler?.({ type: 'destination', destination }))
            })

            this.setCurrentJourneyLocation(realtimePoint)
        },
        setCurrentJourneyLocation(point) {
            if (!this.journeyMapLayer) return
            if (this.currentJourneyMarker) this.journeyMapLayer.removeLayer(this.currentJourneyMarker)
            this.currentJourneyMarker = null
            if (!point) return
            this.currentJourneyMarker = L.circleMarker([point.latitude, point.longitude], {
                radius: 10,
                color: '#fff',
                weight: 3,
                fillColor: point.isSimulated ? '#f59e0b' : '#7c3aed',
                fillOpacity: 1,
            }).addTo(this.journeyMapLayer)
            this.currentJourneyMarker.bindPopup(point.isSimulated
                ? '<strong>Lokasi terkini · Simulasi</strong>'
                : '<strong>Lokasi terkini · Real-time</strong>')
            this.currentJourneyMarker.on('click', () => this.journeyPointHandler?.({ type: 'current', point }))
        },
        setMarkers(markers) {
            if (!this.markerLayer) return
            this.markerLayer.clearLayers()
            markers.forEach((employee) => {
                if (!employee.location) return
                const initials = employee.name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()
                const className = employee.status === 'live' ? 'employee-pin' : 'employee-pin is-offline'
                const marker = L.marker([employee.location.latitude, employee.location.longitude], {
                    icon: L.divIcon({ className: '', html: `<span class="${className}">${escapeHtml(initials)}</span>`, iconSize: [34, 34], iconAnchor: [17, 17] }),
                }).addTo(this.markerLayer)
                marker.bindPopup(`<strong>${escapeHtml(employee.name)}</strong><br>${employee.status === 'live' ? 'Online' : 'Offline'}${employee.location.isSimulated ? ' · Simulasi' : ''}`)
                marker.on('click', () => this.markerSelectHandler?.(employee.id))
            })
        },
        setDestinations(destinations) {
            if (!this.destinationLayer) return
            this.destinationLayer.clearLayers()
            destinations.forEach(({ destination, employee }) => {
                const marker = L.marker([destination.latitude, destination.longitude], {
                    icon: L.divIcon({
                        className: '',
                        html: `<span class="grid size-8 place-items-center rounded-full border-2 border-white bg-indigo-600 text-base shadow">${destinationGlyph(destination.icon)}</span>`,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                    }),
                }).addTo(this.destinationLayer)
                marker.bindTooltip(escapeHtml(destination.name), {
                    permanent: true,
                    direction: 'right',
                    offset: [8, 0],
                    className: 'destination-tooltip',
                })
                marker.on('click', () => this.destinationSelectHandler?.({ destination, employee }))
            })
        },
        setAnnotations(items, visible = true) {
            if (!this.annotationLayer) return
            this.annotationLayer.clearLayers()
            if (!visible) return
            items.forEach((item) => {
                const marker = L.marker([item.latitude, item.longitude], { icon: savedPlaceIcon }).addTo(this.annotationLayer)
                marker.bindPopup(`<strong>${escapeHtml(item.name)}</strong>${item.description ? `<br>${escapeHtml(item.description)}` : ''}`)
            })
        },
        setVehicle(position, options = {}) {
            if (!this.instance) return
            if (this.vehicleMarker) this.instance.removeLayer(this.vehicleMarker)
            this.vehicleMarker = L.circleMarker(position, {
                radius: 8,
                color: '#fff',
                weight: 3,
                fillColor: options.isSimulated ? '#f59e0b' : '#2563eb',
                fillOpacity: 1,
            }).addTo(this.instance)
        },
        setUserLocation(position) {
            if (!this.instance) return
            if (this.userLocationMarker) this.instance.removeLayer(this.userLocationMarker)
            this.userLocationMarker = L.circleMarker(position, {
                radius: 9,
                color: '#fff',
                weight: 3,
                fillColor: '#7c3aed',
                fillOpacity: 1,
            }).addTo(this.instance)
            this.userLocationMarker.bindPopup('Lokasi perangkat ini')
        },
        fitRoute(options = {}) {
            const bounds = L.featureGroup([this.routeLayer, this.historyLayer, this.markerLayer, this.destinationLayer, this.annotationLayer]).getBounds()
            if (bounds.isValid()) this.instance.fitBounds(bounds, { padding: [28, 28], maxZoom: 16, ...options })
        },
        setCamera(center, zoom = 14, animate = true) {
            this.instance?.setView(center, zoom, { animate })
        },
        panTo(center) {
            this.instance?.panTo(center, { animate: false })
        },
        flyTo(center, zoom = 16) {
            this.instance?.flyTo(center, zoom)
        },
        follow(position, enabled) {
            if (enabled) this.setCamera(position, Math.max(this.instance?.getZoom() ?? 14, 16))
        },
        onMapClick(handler) {
            this.clickHandler = handler
        },
        onMarkerSelect(handler) {
            this.markerSelectHandler = handler
        },
        onJourneyPointSelect(handler) {
            this.journeyPointHandler = handler
        },
        onDestinationSelect(handler) {
            this.destinationSelectHandler = handler
        },
    }

    const LocationAPI = {
        getCurrentPosition(options = {}) {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('Browser ini tidak mendukung GPS.'))
                    return
                }
                navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 15000, ...options })
            })
        },
        watchPosition(onUpdate, onError, options = {}) {
            if (!navigator.geolocation) throw new Error('Browser ini tidak mendukung GPS.')
            return navigator.geolocation.watchPosition(onUpdate, onError, { enableHighAccuracy: true, maximumAge: 5000, ...options })
        },
    }

    window.LocationMap = {
        search: GeocodingAPI.search,
        reverse: GeocodingAPI.reverse,
        getRoute: RoutingAPI.getRoute,
        map: MapController,
        getCurrentPosition: LocationAPI.getCurrentPosition,
        watchPosition: LocationAPI.watchPosition,
        geo: LocationGeo,
    }

    const elements = {
        employeeSelect: document.getElementById('employee-select'),
        savedPlaceSearch: document.getElementById('saved-place-search'),
        savedPlaceResults: document.getElementById('saved-place-results'),
        simulationEmployee: document.getElementById('simulation-employee'),
        employeeList: document.getElementById('employee-list'),
        employeeEmpty: document.getElementById('employee-empty'),
        activityList: document.getElementById('activity-list'),
        activityEmpty: document.getElementById('activity-empty'),
        employeeDetail: document.getElementById('employee-detail'),
        historyStatus: document.getElementById('history-status'),
        journeyDialog: document.getElementById('journey-dialog'),
        journeyDialogEmployee: document.getElementById('journey-dialog-employee'),
        journeyDay: document.getElementById('journey-day'),
        journeyMonth: document.getElementById('journey-month'),
        journeyYear: document.getElementById('journey-year'),
        journeySummary: document.getElementById('journey-summary'),
        journeyLastStatus: document.getElementById('journey-last-status'),
        journeyTimeline: document.getElementById('journey-timeline'),
        journeyCount: document.getElementById('journey-count'),
        journeyPointDetail: document.getElementById('journey-point-detail'),
        assignmentMessage: document.getElementById('assignment-message'),
        mapStatus: document.getElementById('map-status'),
        employeeDestinations: document.getElementById('employee-destinations'),
    }
    let employees = config.employees ?? []
    let places = config.places ?? []
    let selectedEmployeeId = config.isManager ? null : config.selfEmployeeId
    let targetPoint = null
    let targetMarker = null
    let trackingWatchId = null
    let simulationTimer = null
    let simulationSendPromise = null
    let simulationResetting = false
    let simulationIndex = 0
    let simulationPath = []
    let lastPositionSentAt = 0
    let trackingEnabled = false
    let placeSearchTimer = null
    let placeSearchRequest = 0
    let lastGeocodeRequestAt = 0
    let employeeSearchTerm = ''
    let currentJourney = { points: [], visits: [], events: [], assignments: [], date: null, truncated: false }
    let selectedJourneyPoint = null
    let journeyRequestId = 0
    let pendingDestinationMoveId = null
    const geocodeCache = new Map()

    const getSelectedEmployee = () => employees.find((employee) => String(employee.id) === String(selectedEmployeeId))
    const formatTime = (date) => date ? new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(date)) : '-'
    const formatDate = (date) => new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(`${date}T12:00:00`))
    const formatDuration = (milliseconds) => {
        const totalMinutes = Math.max(0, Math.floor((milliseconds || 0) / 60000))
        const hours = Math.floor(totalMinutes / 60)
        const minutes = totalMinutes % 60
        if (hours === 0) return `${minutes} mnt`
        return minutes ? `${hours} jam ${minutes} mnt` : `${hours} jam`
    }
    const formatDistance = (meters) => meters >= 1000
        ? `${(meters / 1000).toFixed(2)} km`
        : `${Math.round(meters)} m`
    const localDateString = (date = new Date()) => {
        const parts = new Intl.DateTimeFormat('en-CA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).formatToParts(date)
        const values = Object.fromEntries(parts.map((part) => [part.type, part.value]))
        return `${values.year}-${values.month}-${values.day}`
    }
    const relativeTime = (date) => {
        if (!date) return 'Belum ada data'
        const minutes = Math.max(0, Math.floor((Date.now() - new Date(date).getTime()) / 60000))
        if (minutes < 1) return 'Baru saja'
        if (minutes < 60) return `${minutes} menit lalu`
        return `${Math.floor(minutes / 60)} jam lalu`
    }
    const statusBadge = (employee) => employee.status === 'live'
        ? '<span class="rounded-full bg-green-50 px-2.5 py-1 text-[10px] font-medium text-green-700">Online</span>'
        : '<span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-medium text-gray-500">Offline</span>'

    const renderEmployeeSelectors = () => {
        if (elements.employeeSelect) {
            const selectedValue = selectedEmployeeId ?? ''
            elements.employeeSelect.innerHTML = '<option value="">Pilih karyawan</option>' + employees.map((employee) =>
                `<option value="${employee.id}" ${String(employee.id) === String(selectedValue) ? 'selected' : ''}>${escapeHtml(employee.name)}</option>`,
            ).join('')
        }
        if (elements.simulationEmployee) {
            const previous = elements.simulationEmployee.value
            elements.simulationEmployee.innerHTML = '<option value="">Pilih karyawan</option>' + employees.map((employee) =>
                `<option value="${employee.id}">${escapeHtml(employee.name)}</option>`,
            ).join('')
            if (employees.some((employee) => String(employee.id) === previous)) elements.simulationEmployee.value = previous
            else if (selectedEmployeeId) elements.simulationEmployee.value = selectedEmployeeId
        }
    }

    const renderPlaces = () => {
        renderSavedPlaceResults(elements.savedPlaceSearch?.value ?? '', document.activeElement === elements.savedPlaceSearch)
    }

    const showSavedPlaceResults = (visible) => {
        if (!elements.savedPlaceSearch || !elements.savedPlaceResults) return
        elements.savedPlaceSearch.setAttribute('aria-expanded', String(visible))
        elements.savedPlaceResults.classList.toggle('hidden', !visible)
    }

    const renderSavedPlaceResults = (query = '', visible = true) => {
        if (!elements.savedPlaceResults) return
        const normalized = query.trim().toLocaleLowerCase('id')
        const matches = places.filter((place) =>
            `${place.name} ${place.description ?? ''}`.toLocaleLowerCase('id').includes(normalized),
        )
        elements.savedPlaceResults.replaceChildren()
        if (matches.length === 0) {
            const empty = document.createElement('p')
            empty.className = 'px-3 py-2 text-xs text-gray-500'
            empty.textContent = places.length ? 'Lokasi tersimpan tidak ditemukan.' : 'Belum ada lokasi tersimpan.'
            elements.savedPlaceResults.append(empty)
        } else {
            matches.forEach((place) => {
                const button = document.createElement('button')
                button.type = 'button'
                button.role = 'option'
                button.className = 'block w-full rounded-md px-3 py-2 text-left hover:bg-blue-50'
                button.innerHTML = `<span class="block text-xs font-medium text-gray-800">${escapeHtml(place.name)}</span><span class="mt-0.5 block truncate text-[10px] text-gray-500">${escapeHtml(place.description || `${place.latitude.toFixed(5)}, ${place.longitude.toFixed(5)}`)}</span>`
                button.addEventListener('click', () => {
                    elements.savedPlaceSearch.value = place.name
                    showSavedPlaceResults(false)
                    setTarget(place)
                })
                elements.savedPlaceResults.append(button)
            })
        }
        showSavedPlaceResults(visible)
    }

    const renderEmployees = () => {
        const normalizedSearch = employeeSearchTerm.trim().toLocaleLowerCase('id')
        const filteredEmployees = normalizedSearch
            ? employees.filter((employee) => employee.name.toLocaleLowerCase('id').includes(normalizedSearch))
            : employees
        const liveCount = employees.filter((employee) => employee.status === 'live').length
        document.getElementById('employee-count').textContent = employees.length
        document.getElementById('live-count').textContent = liveCount
        document.getElementById('assignment-count').textContent = employees.reduce(
            (total, employee) => total + (employee.assignments?.length ?? (employee.assignment ? 1 : 0)),
            0,
        )
        document.getElementById('place-count').textContent = places.length
        document.getElementById('table-count').textContent = normalizedSearch
            ? `${filteredEmployees.length} dari ${employees.length} karyawan`
            : `${employees.length} karyawan`
        elements.employeeEmpty?.classList.toggle('hidden', filteredEmployees.length > 0)
        if (elements.employeeEmpty && normalizedSearch && filteredEmployees.length === 0) {
            elements.employeeEmpty.textContent = `Tidak ada karyawan dengan nama "${employeeSearchTerm}".`
        } else if (elements.employeeEmpty) {
            elements.employeeEmpty.textContent = 'Belum ada data karyawan untuk ditampilkan.'
        }
        elements.employeeList.innerHTML = filteredEmployees.map((employee) => {
            const locationName = employee.assignment?.name ?? (employee.location ? 'Lokasi terakhir' : 'Belum ada data')
            const coordinates = employee.location
                ? `${employee.location.latitude.toFixed(5)}, ${employee.location.longitude.toFixed(5)}`
                : '-'
            const photo = employee.photo
                ? `<img src="${escapeHtml(employee.photo)}" alt="" class="size-9 rounded-full object-cover">`
                : `<span class="grid size-9 place-items-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">${escapeHtml(employee.name.slice(0, 1))}</span>`

            return `<tr data-select-employee="${employee.id}" tabindex="0" role="button" aria-label="Pilih karyawan ${escapeHtml(employee.name)}" aria-pressed="${String(employee.id) === String(selectedEmployeeId)}" class="cursor-pointer outline-none transition-colors hover:bg-blue-50/70 focus-visible:bg-blue-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 ${String(employee.id) === String(selectedEmployeeId) ? 'bg-blue-50/70' : ''}">
                <td class="px-4 py-3 sm:px-5"><span class="flex items-center gap-2.5 text-left">${photo}<span><span class="block font-medium text-gray-900">${escapeHtml(employee.name)}</span><span class="text-[10px] text-gray-500">${employee.location?.isSimulated ? 'Data simulasi' : 'Karyawan'}</span></span></span></td>
                <td class="px-3 py-3 text-gray-600">${escapeHtml(employee.department)}</td>
                <td class="px-3 py-3"><span class="block max-w-[130px] truncate text-gray-800">${escapeHtml(locationName)}</span><span class="text-[10px] text-gray-500">${escapeHtml(coordinates)}</span></td>
                <td class="px-3 py-3">${statusBadge(employee)}</td>
                <td class="whitespace-nowrap px-3 py-3 text-gray-600"><span class="block">${formatTime(employee.location?.updatedAt)}</span><span class="text-[10px] text-gray-400">${relativeTime(employee.location?.updatedAt)}</span></td>
            </tr>`
        }).join('')

        elements.activityList.innerHTML = employees
            .filter((employee) => employee.location)
            .sort((a, b) => new Date(b.location.updatedAt) - new Date(a.location.updatedAt))
            .slice(0, 8)
            .map((employee) => `<li class="flex items-center gap-2.5 py-3">
                <span class="w-10 shrink-0 text-[10px] tabular-nums text-gray-500">${formatTime(employee.location.updatedAt)}</span>
                <span class="grid size-8 shrink-0 place-items-center rounded-full ${employee.location.isSimulated ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'} text-[10px] font-semibold">${employee.location.isSimulated ? 'SIM' : escapeHtml(employee.name.slice(0, 1))}</span>
                <span class="min-w-0 flex-1"><span class="block truncate text-xs font-medium text-gray-800">${escapeHtml(employee.name)}</span><span class="block truncate text-[10px] text-gray-400">${employee.location.isSimulated ? 'Lokasi simulasi' : employee.assignment?.name ?? 'Berbagi lokasi'}</span></span>
                ${statusBadge(employee)}
            </li>`).join('')

        elements.activityEmpty?.classList.toggle('hidden', employees.some((employee) => employee.location))
        renderEmployeeSelectors()
        MapController.setMarkers(employees)
        const selectedEmployee = getSelectedEmployee()
        MapController.setDestinations(selectedEmployee
            ? (selectedEmployee.assignments ?? (selectedEmployee.assignment ? [selectedEmployee.assignment] : []))
                .map((destination) => ({ destination, employee: selectedEmployee }))
            : [],
        )
        MapController.setAnnotations(places)
        renderEmployeeDetail()
        renderEmployeeDestinations()
    }

    const renderEmployeeDetail = () => {
        const employee = getSelectedEmployee()
        const status = document.getElementById('detail-status')
        if (!employee) {
            elements.employeeDetail.innerHTML = '<p class="py-8 text-center text-xs text-gray-500">Pilih karyawan untuk melihat detail lokasi.</p>'
            status.textContent = 'Belum dipilih'
            status.className = 'rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-medium text-gray-500'
            return
        }

        status.textContent = employee.status === 'live' ? 'Online' : 'Offline'
        status.className = `rounded-full px-2.5 py-1 text-[10px] font-medium ${employee.status === 'live' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`
        const location = employee.location
        const photo = employee.photo
            ? `<img src="${escapeHtml(employee.photo)}" alt="" class="size-12 rounded-full object-cover">`
            : `<span class="grid size-12 place-items-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">${escapeHtml(employee.name.slice(0, 1))}</span>`
        elements.employeeDetail.innerHTML = `<div class="flex items-center gap-3">${photo}<div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-900">${escapeHtml(employee.name)}</p><p class="mt-0.5 truncate text-xs text-gray-500">${escapeHtml(employee.department)}</p></div></div>
            <dl class="mt-4 space-y-3 text-xs">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tujuan</dt><dd class="text-right font-medium text-gray-800">${escapeHtml(employee.assignment?.name ?? 'Belum ditentukan')}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pembaruan</dt><dd class="text-right text-gray-700">${escapeHtml(relativeTime(location?.updatedAt))}${location?.isSimulated ? ' · Simulasi' : ''}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Koordinat</dt><dd class="text-right font-medium tabular-nums text-gray-800">${location ? `${location.latitude.toFixed(6)}, ${location.longitude.toFixed(6)}` : '-'}</dd></div>
            </dl>
            ${location?.isSimulated ? '<p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[10px] text-amber-800">Data ini berasal dari mode simulasi, bukan GPS aktual.</p>' : ''}
            ${config.isManager ? '<button type="button" id="show-history" class="mt-4 w-full rounded-lg bg-indigo-50 px-3 py-2.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Lihat Detail</button>' : ''}`
    }

    const renderEmployeeDestinations = () => {
        if (!elements.employeeDestinations) return
        const employee = getSelectedEmployee()
        if (!employee) {
            elements.employeeDestinations.innerHTML = ''
            return
        }
        const assignments = employee.assignments ?? (employee.assignment ? [employee.assignment] : [])
        elements.employeeDestinations.innerHTML = `<h3 class="text-xs font-semibold text-gray-700">Tujuan ${escapeHtml(employee.name)} <span class="font-normal text-gray-400">(${assignments.length})</span></h3>
            ${assignments.length ? `<ul class="grid gap-2 sm:grid-cols-2">${assignments.map((destination) => `<li class="flex min-w-0 items-center gap-2 rounded-lg border border-gray-100 bg-white px-3 py-2">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-indigo-50 text-base">${destinationGlyph(destination.icon)}</span>
                <span class="min-w-0 flex-1"><span class="block truncate text-xs font-medium text-gray-800">${escapeHtml(destination.name)}</span><span class="block truncate text-[10px] text-gray-500">${escapeHtml(destination.description || `${destination.latitude.toFixed(5)}, ${destination.longitude.toFixed(5)}`)}</span></span>
                <button type="button" data-edit-destination="${destination.id}" class="rounded px-2 py-1 text-[10px] font-medium text-blue-700 hover:bg-blue-50">Edit</button>
                <button type="button" data-delete-destination="${destination.id}" class="rounded px-2 py-1 text-[10px] font-medium text-red-600 hover:bg-red-50">Hapus</button>
            </li>`).join('')}</ul>` : '<p class="text-[11px] text-gray-500">Belum ada tujuan untuk karyawan ini.</p>'}`
    }

    const savedPlaceForPoint = (point) => places
        .map((place) => ({
            place,
            distance: LocationGeo.distance(
                [point.latitude, point.longitude],
                [place.latitude, place.longitude],
            ),
        }))
        .filter((match) => match.distance <= 200)
        .sort((a, b) => a.distance - b.distance)[0]?.place

    const buildJourney = (points) => {
        const totalDistanceMeters = points.slice(1).reduce((total, point, index) =>
            total + LocationGeo.distance(
                [points[index].latitude, points[index].longitude],
                [point.latitude, point.longitude],
            ), 0)
        const startedAt = points[0]?.recordedAt
        const endedAt = points.at(-1)?.recordedAt
        const durationMs = startedAt && endedAt
            ? Math.max(0, new Date(endedAt).getTime() - new Date(startedAt).getTime())
            : 0
        const groups = []

        points.forEach((point, index) => {
            const timestamp = new Date(point.recordedAt).getTime()
            const group = groups.at(-1)
            const center = group && [
                group.latitudeTotal / group.points.length,
                group.longitudeTotal / group.points.length,
            ]
            const closeToPrevious = group
                && timestamp - group.lastTimestamp <= 30 * 60 * 1000
                && LocationGeo.distance(center, [point.latitude, point.longitude]) <= 100

            if (!closeToPrevious) {
                groups.push({
                    points: [point],
                    startIndex: index,
                    endIndex: index,
                    latitudeTotal: point.latitude,
                    longitudeTotal: point.longitude,
                    lastTimestamp: timestamp,
                })
                return
            }

            group.points.push(point)
            group.endIndex = index
            group.latitudeTotal += point.latitude
            group.longitudeTotal += point.longitude
            group.lastTimestamp = timestamp
        })

        const visits = groups
            .filter((group) => group.lastTimestamp - new Date(group.points[0].recordedAt).getTime() >= 5 * 60 * 1000)
            .map((group) => {
                const first = group.points[0]
                const last = group.points.at(-1)
                const latitude = group.latitudeTotal / group.points.length
                const longitude = group.longitudeTotal / group.points.length
                const savedPlace = savedPlaceForPoint({ latitude, longitude })
                const durationMs = Math.max(0, group.lastTimestamp - new Date(first.recordedAt).getTime())

                return {
                    type: 'visit',
                    startIndex: group.startIndex,
                    endIndex: group.endIndex,
                    latitude,
                    longitude,
                    name: savedPlace?.name ?? `Lokasi sekitar ${latitude.toFixed(5)}, ${longitude.toFixed(5)}`,
                    description: savedPlace?.description ?? null,
                    arrivedAt: first.recordedAt,
                    departedAt: last.recordedAt,
                    durationMs,
                    sampleCount: group.points.length,
                    isSimulated: group.points.some((point) => point.isSimulated),
                }
            })

        const events = []
        let cursor = 0
        const addTravel = (startIndex, endIndex) => {
            if (endIndex <= startIndex) return
            const segment = points.slice(startIndex, endIndex + 1)
            if (segment.length < 2) return
            const distanceMeters = segment.slice(1).reduce((total, point, index) =>
                total + LocationGeo.distance(
                    [segment[index].latitude, segment[index].longitude],
                    [point.latitude, point.longitude],
                ), 0)
            const durationMs = Math.max(0, new Date(segment.at(-1).recordedAt) - new Date(segment[0].recordedAt))
            events.push({
                type: 'travel',
                startIndex,
                endIndex,
                point: segment[0],
                from: segment[0],
                to: segment.at(-1),
                startedAt: segment[0].recordedAt,
                endedAt: segment.at(-1).recordedAt,
                distanceMeters,
                durationMs,
                isSimulated: segment.some((point) => point.isSimulated),
            })
        }

        visits.forEach((visit) => {
            addTravel(cursor, visit.startIndex)
            events.push(visit)
            cursor = visit.endIndex
        })
        addTravel(cursor, points.length - 1)

        return {
            totalDistanceMeters,
            durationMs,
            startedAt,
            endedAt,
            visits,
            events,
        }
    }

    const selectedDate = () => {
        const date = new Date()
        date.setFullYear(Number(elements.journeyYear.value), Number(elements.journeyMonth.value) - 1, Number(elements.journeyDay.value))
        return localDateString(date)
    }

    const populateJourneyDateSelectors = () => {
        const today = new Date()
        const monthNames = new Intl.DateTimeFormat('id-ID', { month: 'long' })
        elements.journeyDay.innerHTML = Array.from({ length: 31 }, (_, index) =>
            `<option value="${index + 1}">${String(index + 1).padStart(2, '0')}</option>`,
        ).join('')
        elements.journeyMonth.innerHTML = Array.from({ length: 12 }, (_, index) => {
            const month = index + 1
            return `<option value="${month}">${monthNames.format(new Date(2020, index, 1))}</option>`
        }).join('')
        const currentYear = today.getFullYear()
        elements.journeyYear.innerHTML = Array.from({ length: 51 }, (_, index) => currentYear - index)
            .map((year) => `<option value="${year}">${year}</option>`)
            .join('')
        elements.journeyDay.value = String(today.getDate())
        elements.journeyMonth.value = String(today.getMonth() + 1)
        elements.journeyYear.value = String(currentYear)
    }

    const clampJourneyDay = () => {
        const daysInMonth = new Date(
            Number(elements.journeyYear.value),
            Number(elements.journeyMonth.value),
            0,
        ).getDate()
        if (Number(elements.journeyDay.value) > daysInMonth) {
            elements.journeyDay.value = String(daysInMonth)
        }
    }

    const changeJourneyDate = () => {
        clampJourneyDay()
        loadHistory()
    }

    const showJourneyPoint = (event) => {
        const detail = elements.journeyPointDetail
        if (!event || !detail) return
        selectedJourneyPoint = event
        const visit = event.visit ?? (event.type === 'visit' ? event : null)
        const destination = event.destination
        const point = visit
            ? { latitude: visit.latitude, longitude: visit.longitude }
            : destination ?? event.point ?? event.from
        if (!point) return
        const title = destination
            ? `${destinationGlyph(destination.icon)} ${destination.name}`
            : visit?.name ?? ({
            start: 'Lokasi awal',
            end: 'Lokasi terakhir tercatat',
            current: event.point?.isSimulated ? 'Lokasi terkini · Simulasi' : 'Lokasi terkini · Real-time',
        }[event.type] ?? 'Titik perjalanan')
        const arrivedAt = destination?.visitedAt ?? visit?.arrivedAt ?? event.startedAt ?? event.point?.recordedAt ?? event.point?.updatedAt ?? event.from?.recordedAt
        const departedAt = visit?.departedAt ?? event.endedAt ?? event.point?.recordedAt ?? event.point?.updatedAt ?? event.to?.recordedAt
        const durationMs = visit?.durationMs ?? event.durationMs ?? 0

        detail.classList.remove('hidden')
        detail.innerHTML = `<div class="flex items-start justify-between gap-2"><h4 class="text-xs font-semibold text-gray-900">${escapeHtml(title)}</h4><button type="button" id="close-journey-point" class="text-gray-400 hover:text-gray-700" aria-label="Tutup detail">&times;</button></div>
            ${(destination?.description ?? visit?.description) ? `<p class="mt-1 text-[10px] text-gray-600">${escapeHtml(destination?.description ?? visit.description)}</p>` : ''}
            <dl class="mt-2 space-y-1.5 text-[10px]">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Koordinat</dt><dd class="text-right font-medium tabular-nums text-gray-700">${point.latitude.toFixed(6)}, ${point.longitude.toFixed(6)}</dd></div>
                ${destination ? `<div class="flex justify-between gap-3"><dt class="text-gray-500">Status tujuan</dt><dd class="text-right text-gray-700">${destination.visited ? `Dikunjungi${destination.visitedAt ? ` · ${formatTime(destination.visitedAt)}` : ''}` : 'Belum dikunjungi'}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Geofence</dt><dd class="text-right text-gray-700">Radius ${destination.geofenceRadiusMeters} m</dd></div>
                ${destination.visitIsSimulated ? '<div class="font-medium text-amber-700">Kunjungan berdasarkan data simulasi</div>' : ''}` : ''}
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Tiba / tercatat</dt><dd class="text-right text-gray-700">${arrivedAt ? formatTime(arrivedAt) : '-'}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pergi / terakhir tercatat</dt><dd class="text-right text-gray-700">${departedAt ? formatTime(departedAt) : '-'}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Durasi</dt><dd class="text-right text-gray-700">${formatDuration(durationMs)}</dd></div>
                ${event.type === 'travel' ? `<div class="flex justify-between gap-3"><dt class="text-gray-500">Jarak GPS</dt><dd class="text-right text-gray-700">${formatDistance(event.distanceMeters)}</dd></div>` : ''}
                ${visit?.isSimulated || event.point?.isSimulated ? '<div class="font-medium text-amber-700">Data simulasi</div>' : ''}
            </dl>`
        document.getElementById('close-journey-point')?.addEventListener('click', () => detail.classList.add('hidden'))
        MapController.panToJourney([point.latitude, point.longitude])
    }

    const renderJourney = (points, date, truncated = false, assignments = []) => {
        const employee = getSelectedEmployee()
        const summary = buildJourney(points)
        const isToday = date === localDateString()
        const isLive = isToday && employee?.status === 'live' && employee.location
        const realtimePoint = isLive ? employee.location : null
        currentJourney = { points, visits: summary.visits, events: summary.events, assignments, date, truncated }

        MapController.setJourney(points, summary.visits, realtimePoint, assignments)
        elements.journeyTimeline.replaceChildren()
        elements.journeySummary.replaceChildren()
        elements.journeyPointDetail.classList.add('hidden')
        selectedJourneyPoint = null

        const destinationTimeline = assignments.map((destination) => `
            <button type="button" data-journey-destination="${destination.id}" class="block w-full rounded-lg border border-indigo-100 bg-indigo-50/50 px-3 py-2.5 text-left transition hover:border-indigo-200 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                <span class="flex items-center justify-between gap-2"><span class="min-w-0 truncate text-[10px] font-semibold text-gray-800">${destinationGlyph(destination.icon)} ${escapeHtml(destination.name)}</span><span class="shrink-0 rounded-full ${destination.visited ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'} px-2 py-0.5 text-[9px] font-medium">${destination.visited ? 'Dikunjungi' : 'Belum dikunjungi'}</span></span>
                <span class="mt-1 block text-[9px] text-gray-500">${destination.visited && destination.visitedAt ? `Tiba ${formatTime(destination.visitedAt)}` : `Tujuan admin · geofence ${destination.geofenceRadiusMeters} m`}</span>
                ${destination.description ? `<span class="mt-1 block text-[9px] text-gray-600">${escapeHtml(destination.description)}</span>` : ''}
                ${destination.visitIsSimulated ? '<span class="mt-1 block text-[9px] font-medium text-amber-700">Kunjungan berdasarkan data simulasi</span>' : ''}
            </button>`).join('')

        if (!points.length) {
            elements.historyStatus.textContent = `Tidak ada titik lokasi tersimpan pada ${formatDate(date)}.`
            elements.journeyCount.textContent = ''
            elements.journeySummary.innerHTML = '<p class="col-span-2 rounded-lg bg-slate-50 px-3 py-4 text-center text-[11px] text-gray-500">Belum ada aktivitas GPS untuk tanggal ini.</p>'
            elements.journeyLastStatus.innerHTML = '<span class="font-medium text-gray-700">Status lokasi:</span> Tidak ada data pada tanggal ini.'
            elements.journeyTimeline.innerHTML = `${destinationTimeline}${assignments.length ? '' : '<p class="rounded-lg bg-slate-50 px-3 py-4 text-center text-[11px] text-gray-500">Timeline akan tersedia setelah lokasi karyawan tercatat.</p>'}`
            return
        }

        elements.historyStatus.textContent = `${points.length}${truncated ? '+' : ''} titik GPS · ${formatDate(date)}${points.some((point) => point.isSimulated) ? ' · Termasuk data simulasi' : ''}`
        elements.journeyCount.textContent = `${summary.events.length} aktivitas`
        const summaryItems = [
            ['Jarak tercatat', formatDistance(summary.totalDistanceMeters)],
            ['Durasi tercatat', formatDuration(summary.durationMs)],
            ['Lokasi dikunjungi', String(summary.visits.length)],
            ['Mulai – selesai', `${formatTime(summary.startedAt)} – ${formatTime(summary.endedAt)}`],
        ]
        elements.journeySummary.innerHTML = summaryItems.map(([label, value]) =>
            `<div class="rounded-lg bg-slate-50 px-3 py-2"><p class="text-[9px] text-gray-500">${label}</p><p class="mt-0.5 text-xs font-semibold text-gray-800">${value}</p></div>`,
        ).join('')

        const lastPoint = points.at(-1)
        const lastLocation = savedPlaceForPoint(lastPoint)?.name
            ?? `${lastPoint.latitude.toFixed(5)}, ${lastPoint.longitude.toFixed(5)}`
        const latestStatus = isLive
            ? (realtimePoint.isSimulated ? 'Lokasi simulasi aktif' : 'Karyawan sedang berbagi lokasi')
            : `Terakhir tercatat ${formatTime(lastPoint.recordedAt)} · Tidak live`
        elements.journeyLastStatus.innerHTML = `<span class="font-medium text-gray-700">Status lokasi terakhir:</span> ${escapeHtml(latestStatus)} <span class="block mt-1 text-gray-500">${escapeHtml(lastLocation)}</span>`

        if (truncated) {
            elements.historyStatus.textContent += ' · Riwayat melebihi batas tampilan 5.000 titik.'
        }

        elements.journeyTimeline.innerHTML = destinationTimeline + summary.events.map((event, index) => {
            if (event.type === 'visit') {
                return `<button type="button" data-journey-event="${index}" class="block w-full rounded-lg border border-gray-100 px-3 py-2.5 text-left transition hover:border-amber-200 hover:bg-amber-50/60 focus-visible:outline-2 focus-visible:outline-blue-500">
                    <span class="flex items-center justify-between gap-2"><span class="text-[10px] font-semibold text-gray-800">${escapeHtml(event.name)}</span><span class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-medium text-amber-700">Kunjungan</span></span>
                    <span class="mt-1.5 flex flex-wrap justify-between gap-x-2 text-[9px] text-gray-500"><span>Tiba ${formatTime(event.arrivedAt)} · Pergi ${formatTime(event.departedAt)}</span><span>${formatDuration(event.durationMs)}</span></span>
                    ${event.isSimulated ? '<span class="mt-1 block text-[9px] font-medium text-amber-700">Data simulasi</span>' : ''}
                </button>`
            }
            return `<button type="button" data-journey-event="${index}" class="block w-full rounded-lg border border-gray-100 bg-slate-50/70 px-3 py-2.5 text-left transition hover:border-blue-200 hover:bg-blue-50/60 focus-visible:outline-2 focus-visible:outline-blue-500">
                <span class="flex items-center justify-between gap-2"><span class="text-[10px] font-semibold text-gray-800">Perjalanan GPS</span><span class="shrink-0 text-[9px] font-medium text-blue-700">${formatDistance(event.distanceMeters)}</span></span>
                <span class="mt-1.5 flex flex-wrap justify-between gap-x-2 text-[9px] text-gray-500"><span>${formatTime(event.startedAt)} – ${formatTime(event.endedAt)}</span><span>${formatDuration(event.durationMs)}</span></span>
                ${event.isSimulated ? '<span class="mt-1 block text-[9px] font-medium text-amber-700">Data simulasi</span>' : ''}
            </button>`
        }).join('')
    }

    const setTarget = (point, marker = true) => {
        targetPoint = point
        renderPlaces()
        if (elements.savedPlaceSearch) {
            elements.savedPlaceSearch.value = point?.name ?? ''
            showSavedPlaceResults(false)
        }
        if (targetMarker) MapController.instance?.removeLayer(targetMarker)
        if (marker && point) {
            targetMarker = L.marker([point.latitude, point.longitude], { icon: selectedPlaceIcon }).addTo(MapController.instance)
            targetMarker.bindPopup(`<strong>${escapeHtml(point.name)}</strong>`).openPopup()
            MapController.panTo([point.latitude, point.longitude])
        }
        updateRoute()
    }

    const updateRoute = async () => {
        const employee = getSelectedEmployee()
        if (!employee?.location || !targetPoint) {
            MapController.setRoute([], { history: false })
            return
        }
        try {
            const result = await RoutingAPI.getRoute([
                [employee.location.latitude, employee.location.longitude],
                [targetPoint.latitude, targetPoint.longitude],
            ])
            const route = result.routes?.[0]
            if (route?.geometry?.coordinates) {
                MapController.setRoute(route.geometry.coordinates, { geoJson: true })
                elements.assignmentMessage && (elements.assignmentMessage.textContent = `${(route.distance / 1000).toFixed(1)} km · estimasi ${Math.max(1, Math.round(route.duration / 60))} menit`)
            }
        } catch (error) {
            elements.assignmentMessage && (elements.assignmentMessage.textContent = `Rute tidak dapat dimuat: ${error.message}`)
        }
    }

    const loadHistory = async (fitRoute = false) => {
        const employee = getSelectedEmployee()
        const requestId = ++journeyRequestId
        if (!employee) {
            currentJourney = { points: [], visits: [], events: [], assignments: [], date: selectedDate(), truncated: false }
            renderJourney([], selectedDate())
            elements.historyStatus.textContent = 'Pilih karyawan untuk melihat riwayat.'
            return
        }
        MapController.setJourney([], [], null, [])
        elements.historyStatus.textContent = 'Memuat riwayat lokasi…'
        const params = new URLSearchParams({ date: selectedDate() })
        try {
            const response = await request(`${config.routes.historyPrefix}${employee.id}/history?${params}`)
            if (requestId !== journeyRequestId) return
            renderJourney(response.points, response.date, response.truncated, response.assignments ?? [])
            if (fitRoute && (response.points.length || response.assignments?.length)) {
                window.requestAnimationFrame(() => {
                    MapController.invalidateJourneyMap()
                    MapController.fitJourneyRoute()
                })
            }
        } catch (error) {
            if (requestId !== journeyRequestId) return
            elements.historyStatus.textContent = `Riwayat gagal dimuat: ${error.message}`
        }
    }

    const selectEmployee = (id) => {
        const changed = String(selectedEmployeeId ?? '') !== String(id ?? '')
        selectedEmployeeId = id
        if (changed) {
            targetPoint = null
            if (targetMarker) MapController.instance?.removeLayer(targetMarker)
            targetMarker = null
            MapController.setRoute([], { history: false })
            if (elements.savedPlaceSearch) elements.savedPlaceSearch.value = ''
        }
        if (elements.employeeSelect) elements.employeeSelect.value = String(id ?? '')
        renderEmployees()
        const employee = getSelectedEmployee()
        if (employee?.location) {
            MapController.setVehicle(
                [employee.location.latitude, employee.location.longitude],
                { isSimulated: employee.location.isSimulated },
            )
        }
        const primaryAssignment = employee?.assignments?.[0] ?? employee?.assignment
        if (primaryAssignment) {
            setTarget({ ...primaryAssignment, ephemeral: true })
        } else if (employee?.location) {
            MapController.panTo([employee.location.latitude, employee.location.longitude])
        }
        if (changed && !elements.journeyDialog.classList.contains('hidden')) {
            elements.journeyDialogEmployee.textContent = employee?.name ?? 'Pilih karyawan untuk melihat riwayat.'
            loadHistory(true)
        }
    }

    const refreshTracking = async () => {
        try {
            const response = await request(config.routes.data)
            employees = response.employees
            renderEmployees()
            if (currentJourney.date === localDateString()) {
                const employee = getSelectedEmployee()
                const realtimePoint = employee?.status === 'live'
                    ? { ...employee.location, recordedAt: employee.location.updatedAt }
                    : null
                MapController.setCurrentJourneyLocation(realtimePoint)
                if (employee && currentJourney.points.length) {
                    const lastPoint = currentJourney.points.at(-1)
                    const lastLocation = savedPlaceForPoint(lastPoint)?.name
                        ?? `${lastPoint.latitude.toFixed(5)}, ${lastPoint.longitude.toFixed(5)}`
                    const latestStatus = realtimePoint
                        ? (realtimePoint.isSimulated ? 'Lokasi simulasi aktif' : 'Karyawan sedang berbagi lokasi')
                        : `Terakhir tercatat ${formatTime(lastPoint.recordedAt)} · Tidak live`
                    elements.journeyLastStatus.innerHTML = `<span class="font-medium text-gray-700">Status lokasi terakhir:</span> ${escapeHtml(latestStatus)} <span class="block mt-1 text-gray-500">${escapeHtml(lastLocation)}</span>`
                }
            }
            if (targetPoint) await updateRoute()
            elements.mapStatus.textContent = `Pembaruan otomatis setiap 10 detik · ${new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date())}`
        } catch (error) {
            elements.mapStatus.textContent = `Gagal memperbarui lokasi: ${error.message}`
        }
    }

    const showPlaceDialog = () => {
        const dialog = document.getElementById('place-dialog')
        if (!targetPoint) {
            document.getElementById('place-error').textContent = 'Klik peta atau cari lokasi terlebih dahulu.'
            dialog.classList.remove('hidden')
            dialog.classList.add('flex')
            return
        }
        document.getElementById('place-error').textContent = ''
        document.getElementById('place-coordinates').textContent = `${targetPoint.latitude.toFixed(6)}, ${targetPoint.longitude.toFixed(6)}`
        document.getElementById('place-name').value = targetPoint.name === 'Titik pilihan di peta' ? '' : targetPoint.name
        dialog.classList.remove('hidden')
        dialog.classList.add('flex')
        document.getElementById('place-name').focus()
    }

    const closePlaceDialog = () => {
        const dialog = document.getElementById('place-dialog')
        dialog?.classList.add('hidden')
        dialog?.classList.remove('flex')
    }

    const closeButtons = ['close-place-dialog', 'cancel-place']
    closeButtons.forEach((id) => document.getElementById(id)?.addEventListener('click', closePlaceDialog))
    document.getElementById('place-dialog')?.addEventListener('click', (event) => {
        if (event.target.id === 'place-dialog') closePlaceDialog()
    })

    const closeDestinationDialog = () => {
        const dialog = document.getElementById('destination-dialog')
        dialog?.classList.add('hidden')
        dialog?.classList.remove('flex')
    }
    const openDestinationDialog = (destination = null, mode = 'edit') => {
        const employee = getSelectedEmployee()
        if (!employee) {
            elements.assignmentMessage.textContent = 'Pilih karyawan terlebih dahulu.'
            return
        }
        if (destination) {
            targetPoint = { ...destination, ephemeral: true }
        }
        if (!targetPoint) {
            elements.assignmentMessage.textContent = 'Pilih lokasi dari peta atau pencarian terlebih dahulu.'
            return
        }
        const form = document.getElementById('destination-form')
        form.reset()
        document.getElementById('destination-id').value = destination?.id ?? ''
        document.getElementById('destination-name').value = destination?.name ?? (targetPoint.name === 'Titik pilihan di peta' ? '' : targetPoint.name)
        document.getElementById('destination-description').value = destination?.description ?? targetPoint.description ?? ''
        document.getElementById('destination-icon').value = destination?.icon ?? 'pin'
        document.getElementById('destination-latitude').value = targetPoint.latitude
        document.getElementById('destination-longitude').value = targetPoint.longitude
        document.getElementById('destination-coordinates').textContent = destination
            ? `${destinationGlyph(destination.icon)} ${destination.name}`
            : 'Koordinat diambil otomatis dari titik yang dipilih pada peta atau hasil pencarian.'
        document.getElementById('destination-error').textContent = ''
        const isView = mode === 'view'
        document.getElementById('destination-dialog-title').textContent = isView
            ? 'Detail tujuan lokasi'
            : destination ? 'Edit tujuan karyawan' : 'Tambah tujuan karyawan'
        document.getElementById('destination-preview').classList.toggle('hidden', !isView)
        document.getElementById('destination-fields').classList.toggle('hidden', isView)
        document.getElementById('destination-assigned-employee').classList.toggle('hidden', isView)
        document.getElementById('destination-assigned-employee').textContent = isView ? `Ditugaskan kepada: ${employee.name}` : ''
        document.getElementById('destination-preview-name').textContent = `${destinationGlyph(destination?.icon)} ${destination?.name ?? ''}`
        document.getElementById('destination-preview-employee').textContent = `Ditugaskan kepada: ${employee.name}`
        const description = document.getElementById('destination-preview-description')
        description.textContent = destination?.description ?? ''
        description.classList.toggle('hidden', !destination?.description)
        document.getElementById('destination-preview-coordinates').textContent = `${targetPoint.latitude.toFixed(6)}, ${targetPoint.longitude.toFixed(6)}`
        document.getElementById('destination-edit-coordinate-hint').classList.toggle('hidden', !destination || isView)
        document.getElementById('destination-edit-coordinate-hint').textContent = 'Koordinat saat ini dipertahankan. Gunakan “Pilih titik baru di peta” untuk memindahkan lokasi.'
        document.getElementById('move-destination').classList.toggle('hidden', !destination || isView)
        document.getElementById('edit-destination-dialog').classList.toggle('hidden', !isView)
        document.getElementById('delete-destination-dialog').classList.toggle('hidden', !isView)
        document.getElementById('save-destination').classList.toggle('hidden', isView)
        document.getElementById('cancel-destination').textContent = isView ? 'Tutup' : 'Batal'
        document.getElementById('destination-dialog').classList.remove('hidden')
        document.getElementById('destination-dialog').classList.add('flex')
        if (!isView) document.getElementById('destination-name').focus()
    }
    ;['close-destination-dialog', 'cancel-destination'].forEach((id) =>
        document.getElementById(id)?.addEventListener('click', closeDestinationDialog),
    )
    document.getElementById('destination-dialog')?.addEventListener('click', (event) => {
        if (event.target.id === 'destination-dialog') closeDestinationDialog()
    })
    document.getElementById('edit-destination-dialog')?.addEventListener('click', () => {
        const employee = getSelectedEmployee()
        const id = document.getElementById('destination-id').value
        const destination = employee?.assignments?.find((item) => String(item.id) === String(id))
        if (destination) openDestinationDialog(destination)
    })
    document.getElementById('move-destination')?.addEventListener('click', () => {
        pendingDestinationMoveId = document.getElementById('destination-id').value
        closeDestinationDialog()
        elements.assignmentMessage.textContent = 'Klik titik baru pada peta untuk memindahkan tujuan.'
    })
    document.getElementById('delete-destination-dialog')?.addEventListener('click', async () => {
        const employee = getSelectedEmployee()
        const id = document.getElementById('destination-id').value
        const destination = employee?.assignments?.find((item) => String(item.id) === String(id))
        if (destination) await deleteDestination(employee, destination)
    })
    document.getElementById('assign-route')?.addEventListener('click', () => openDestinationDialog())
    document.getElementById('destination-form')?.addEventListener('submit', async (event) => {
        event.preventDefault()
        const employee = getSelectedEmployee()
        if (!employee || !targetPoint) return
        const id = document.getElementById('destination-id').value
        const data = {
            name: document.getElementById('destination-name').value.trim(),
            description: document.getElementById('destination-description').value.trim() || null,
            icon: document.getElementById('destination-icon').value,
            latitude: Number(document.getElementById('destination-latitude').value),
            longitude: Number(document.getElementById('destination-longitude').value),
            ...(id ? {} : { employee_id: employee.id }),
        }
        const errorElement = document.getElementById('destination-error')
        const button = event.currentTarget.querySelector('[type="submit"]')
        button.disabled = true
        try {
            const response = await request(id
                ? `${config.routes.assignmentPrefix}${id}`
                : config.routes.assignment, {
                method: id ? 'PUT' : 'POST',
                body: JSON.stringify(data),
            })
            const assignments = employee.assignments ?? (employee.assignment ? [employee.assignment] : [])
            if (id) {
                employee.assignments = assignments.map((item) => String(item.id) === String(id) ? response.assignment : item)
            } else {
                employee.assignments = [...assignments, response.assignment]
            }
            employee.assignment = employee.assignments[0] ?? null
            targetPoint = { ...response.assignment, ephemeral: true }
            elements.assignmentMessage.textContent = `Tujuan ${employee.name} ${id ? 'diperbarui' : 'ditambahkan'}: ${response.assignment.name}`
            closeDestinationDialog()
            renderEmployees()
            setTarget(targetPoint)
        } catch (error) {
            errorElement.textContent = error.message
        } finally {
            button.disabled = false
        }
    })
    elements.employeeDestinations?.addEventListener('click', async (event) => {
        const employee = getSelectedEmployee()
        const editButton = event.target.closest('[data-edit-destination]')
        if (editButton && employee) {
            const destination = (employee.assignments ?? []).find(
                (item) => String(item.id) === editButton.dataset.editDestination,
            )
            if (destination) openDestinationDialog(destination)
            return
        }
        const deleteButton = event.target.closest('[data-delete-destination]')
        if (!deleteButton || !employee) return
        const destination = (employee.assignments ?? []).find(
            (item) => String(item.id) === deleteButton.dataset.deleteDestination,
        )
        if (!destination) return
        await deleteDestination(employee, destination)
    })

    const deleteDestination = async (employee, destination) => {
        if (!window.confirm(`Hapus tujuan "${destination.name}" untuk ${employee.name}?`)) return
        try {
            await request(`${config.routes.assignmentPrefix}${destination.id}`, { method: 'DELETE' })
            employee.assignments = employee.assignments.filter((item) => String(item.id) !== String(destination.id))
            employee.assignment = employee.assignments[0] ?? null
            closeDestinationDialog()
            if (String(targetPoint?.id ?? '') === String(destination.id)) {
                targetPoint = employee.assignment ? { ...employee.assignment, ephemeral: true } : null
                if (!targetPoint) MapController.setRoute([], { history: false })
            }
            elements.assignmentMessage.textContent = `Tujuan ${destination.name} dihapus.`
            renderEmployees()
            if (targetPoint) setTarget(targetPoint, false)
        } catch (error) {
            elements.assignmentMessage.textContent = `Tujuan gagal dihapus: ${error.message}`
        }
    }

    MapController.initialize('tracking-map', { center: [-7.4243, 109.2302], zoom: 14 })
    MapController.instance.invalidateSize()
    MapController.onMarkerSelect((id) => selectEmployee(id))
    MapController.onDestinationSelect(({ destination, employee }) => {
        selectEmployee(employee.id)
        openDestinationDialog(destination, 'view')
    })
    MapController.onMapClick(async (coordinate) => {
        if (!config.isManager) return
        const point = { latitude: coordinate[0], longitude: coordinate[1], name: 'Titik pilihan di peta', ephemeral: true }
        const movingDestinationId = pendingDestinationMoveId
        pendingDestinationMoveId = null
        setTarget(point)
        if (movingDestinationId) {
            const employee = getSelectedEmployee()
            const destination = employee?.assignments?.find((item) => String(item.id) === String(movingDestinationId))
            if (destination) {
                openDestinationDialog({ ...destination, latitude: point.latitude, longitude: point.longitude })
                elements.assignmentMessage.textContent = `Titik baru dipilih untuk ${destination.name}; simpan perubahan untuk menerapkannya.`
                return
            }
        }
        elements.assignmentMessage.textContent = 'Titik tujuan dipilih dari peta.'
        try {
            const result = await GeocodingAPI.reverse(coordinate)
            if (result.display_name) {
                point.name = result.name || result.display_name.split(',')[0]
                setTarget(point)
                elements.assignmentMessage.textContent = result.display_name
            }
        } catch {
            elements.assignmentMessage.textContent = 'Titik peta dipilih; nama lokasi dapat ditambahkan sendiri.'
        }
    })

    document.getElementById('employee-search')?.addEventListener('input', (event) => {
        employeeSearchTerm = event.currentTarget.value
        renderEmployees()
    })

    elements.employeeList.addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-select-employee]')
        if (row) selectEmployee(row.dataset.selectEmployee)
    })
    elements.employeeList.addEventListener('keydown', (event) => {
        const row = event.target.closest('tr[data-select-employee]')
        if (row && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault()
            selectEmployee(row.dataset.selectEmployee)
        }
    })

    elements.employeeDetail.addEventListener('click', (event) => {
        if (!event.target.closest('#show-history')) return
        const employee = getSelectedEmployee()
        if (!employee) return
        elements.journeyDialogEmployee.textContent = employee.name
        elements.journeyDialog.classList.remove('hidden')
        elements.journeyDialog.classList.add('flex')
        MapController.initializeJourneyMap('journey-map')
        window.requestAnimationFrame(() => MapController.invalidateJourneyMap())
        loadHistory(true)
    })
    const closeJourneyDialog = () => {
        elements.journeyDialog.classList.add('hidden')
        elements.journeyDialog.classList.remove('flex')
    }
    document.getElementById('close-journey-dialog').addEventListener('click', closeJourneyDialog)
    elements.journeyDialog.addEventListener('click', (event) => {
        if (event.target === elements.journeyDialog) closeJourneyDialog()
    })
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !elements.journeyDialog.classList.contains('hidden')) {
            closeJourneyDialog()
        }
    })
    elements.employeeSelect?.addEventListener('change', (event) => {
        selectEmployee(event.target.value || null)
    })
    elements.journeyTimeline.addEventListener('click', (event) => {
        const destinationButton = event.target.closest('[data-journey-destination]')
        if (destinationButton) {
            const destination = currentJourney.assignments.find(
                (item) => String(item.id) === destinationButton.dataset.journeyDestination,
            )
            if (destination) showJourneyPoint({ type: 'destination', destination })
            return
        }
        const button = event.target.closest('[data-journey-event]')
        if (button) showJourneyPoint(currentJourney.events[Number(button.dataset.journeyEvent)])
    })
    MapController.onJourneyPointSelect(showJourneyPoint)

    populateJourneyDateSelectors()
    elements.journeyDay.addEventListener('change', changeJourneyDate)
    elements.journeyMonth.addEventListener('change', changeJourneyDate)
    elements.journeyYear.addEventListener('change', changeJourneyDate)
    document.getElementById('journey-today').addEventListener('click', () => {
        const today = new Date()
        elements.journeyDay.value = String(today.getDate())
        elements.journeyMonth.value = String(today.getMonth() + 1)
        elements.journeyYear.value = String(today.getFullYear())
        loadHistory()
    })

    const savedPlaceToggle = document.getElementById('saved-place-toggle')
    savedPlaceToggle?.addEventListener('click', () => {
        const isVisible = !elements.savedPlaceResults.classList.contains('hidden')
        renderSavedPlaceResults(elements.savedPlaceSearch.value, !isVisible)
        if (!isVisible) elements.savedPlaceSearch.focus()
    })
    elements.savedPlaceSearch?.addEventListener('focus', () => {
        renderSavedPlaceResults(elements.savedPlaceSearch.value)
    })
    elements.savedPlaceSearch?.addEventListener('input', () => {
        renderSavedPlaceResults(elements.savedPlaceSearch.value)
    })
    elements.savedPlaceSearch?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') showSavedPlaceResults(false)
        if (event.key === 'Enter') {
            const first = elements.savedPlaceResults.querySelector('[role="option"]')
            if (first) {
                event.preventDefault()
                first.click()
            }
        }
    })

    const searchResults = document.getElementById('search-results')
    const showGeocodeResults = (visible) => {
        const input = document.getElementById('place-search')
        input?.setAttribute('aria-expanded', String(visible))
        searchResults?.classList.toggle('hidden', !visible)
    }

    const searchGeocoding = async (query, requestId) => {
        const cached = geocodeCache.get(query.toLocaleLowerCase('id'))
        if (cached) {
            if (requestId === placeSearchRequest) renderGeocodeResults(cached, requestId)
            return
        }
        const wait = Math.max(0, 1000 - (Date.now() - lastGeocodeRequestAt))
        if (wait) {
            placeSearchTimer = window.setTimeout(() => searchGeocoding(query, requestId), wait)
            return
        }

        lastGeocodeRequestAt = Date.now()
        try {
            const results = await GeocodingAPI.search(query)
            geocodeCache.set(query.toLocaleLowerCase('id'), results)
            renderGeocodeResults(results, requestId)
        } catch (error) {
            if (requestId !== placeSearchRequest) return
            searchResults.replaceChildren()
            const item = document.createElement('p')
            item.className = 'px-3 py-2 text-xs text-red-600'
            item.textContent = `Pencarian gagal: ${error.message}`
            searchResults.append(item)
            showGeocodeResults(true)
        }
    }

    const renderGeocodeResults = (results, requestId) => {
        if (requestId !== placeSearchRequest) return
        searchResults.replaceChildren()
        if (!results.length) {
            const empty = document.createElement('p')
            empty.className = 'px-3 py-2 text-xs text-gray-500'
            empty.textContent = 'Lokasi tidak ditemukan.'
            searchResults.append(empty)
            showGeocodeResults(true)
            return
        }

        results.forEach((result, index) => {
            const button = document.createElement('button')
            const point = {
                name: result.name || result.display_name.split(',')[0],
                latitude: Number(result.lat),
                longitude: Number(result.lon),
                ephemeral: true,
            }
            button.type = 'button'
            button.role = 'option'
            button.className = 'block w-full rounded-md px-3 py-2 text-left hover:bg-blue-50'
            button.innerHTML = `<span class="block text-xs font-medium text-gray-800">${escapeHtml(point.name)}</span><span class="mt-0.5 block truncate text-[10px] text-gray-500">${escapeHtml(result.display_name)}</span>`
            button.addEventListener('click', () => {
                setTarget(point)
                document.getElementById('place-search').value = result.display_name
                elements.assignmentMessage.textContent = result.display_name
                showGeocodeResults(false)
            })
            if (index === 0) button.setAttribute('aria-selected', 'true')
            searchResults.append(button)
        })
        showGeocodeResults(true)
    }

    document.getElementById('place-search')?.addEventListener('input', (event) => {
        const query = event.currentTarget.value.trim()
        window.clearTimeout(placeSearchTimer)
        placeSearchRequest += 1
        const requestId = placeSearchRequest

        if (query.length < 3) {
            searchResults.replaceChildren()
            showGeocodeResults(false)
            return
        }

        searchResults.replaceChildren()
        const hint = document.createElement('p')
        hint.className = 'px-3 py-2 text-xs text-gray-500'
        hint.textContent = 'Mencari lokasi…'
        searchResults.append(hint)
        showGeocodeResults(true)
        placeSearchTimer = window.setTimeout(() => searchGeocoding(query, requestId), 700)
    })
    document.getElementById('place-search')?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') showGeocodeResults(false)
        if (event.key === 'Enter') {
            const first = searchResults.querySelector('[role="option"]')
            if (first) {
                event.preventDefault()
                first.click()
            }
        }
    })
    document.addEventListener('click', (event) => {
        if (!event.target.closest('#saved-place-search, #saved-place-toggle, #saved-place-results')) {
            showSavedPlaceResults(false)
        }
        if (!event.target.closest('#place-search, #search-results')) showGeocodeResults(false)
    })

    document.getElementById('save-place-open')?.addEventListener('click', showPlaceDialog)

    document.getElementById('place-form')?.addEventListener('submit', async (event) => {
        event.preventDefault()
        const errorElement = document.getElementById('place-error')
        if (!targetPoint) {
            errorElement.textContent = 'Pilih titik di peta terlebih dahulu.'
            return
        }
        const formData = new FormData(event.currentTarget)
        try {
            const response = await request(config.routes.places, {
                method: 'POST',
                body: JSON.stringify({
                    name: formData.get('name'),
                    description: formData.get('description'),
                    latitude: targetPoint.latitude,
                    longitude: targetPoint.longitude,
                }),
            })
            places = [...places, response.place]
            setTarget(response.place)
            renderPlaces()
            renderEmployees()
            closePlaceDialog()
            event.currentTarget.reset()
        } catch (error) {
            errorElement.textContent = error.message
        }
    })

    document.getElementById('refresh-map').addEventListener('click', () => refreshTracking())
    document.getElementById('fit-map').addEventListener('click', () => MapController.fitRoute())
    document.getElementById('recenter-map').addEventListener('click', async (event) => {
        const button = event.currentTarget
        const originalLabel = button.textContent
        button.disabled = true
        button.textContent = 'Mencari lokasi…'
        try {
            const position = await LocationAPI.getCurrentPosition({ maximumAge: 0 })
            const coordinate = [position.coords.latitude, position.coords.longitude]
            MapController.setUserLocation(coordinate)
            MapController.panTo(coordinate)
            elements.mapStatus.textContent = `Lokasi perangkat ditemukan · akurasi ±${Math.round(position.coords.accuracy)} m`
        } catch (error) {
            elements.mapStatus.textContent = `Lokasi perangkat tidak tersedia: ${error.message}`
        } finally {
            button.disabled = false
            button.textContent = originalLabel
        }
    })

    const mapShell = document.getElementById('map-shell')
    const fullscreenButton = document.getElementById('fullscreen-map')
    const updateFullscreenState = () => {
        const isFullscreen = document.fullscreenElement === mapShell
        fullscreenButton.textContent = isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'
        fullscreenButton.setAttribute('aria-label', isFullscreen ? 'Keluar dari layar penuh' : 'Buka peta layar penuh')
        window.setTimeout(() => MapController.instance?.invalidateSize({ pan: false }), 150)
    }
    fullscreenButton.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement === mapShell) {
                await document.exitFullscreen()
            } else {
                await mapShell.requestFullscreen()
            }
        } catch (error) {
            elements.mapStatus.textContent = `Mode layar penuh tidak tersedia: ${error.message}`
        }
    })
    document.addEventListener('fullscreenchange', updateFullscreenState)

    document.getElementById('toggle-tracking')?.addEventListener('click', async (event) => {
        const button = event.currentTarget
        const message = document.getElementById('tracking-consent')
        if (trackingEnabled) {
            navigator.geolocation.clearWatch(trackingWatchId)
            trackingWatchId = null
            trackingEnabled = false
            button.textContent = 'Mulai berbagi lokasi'
            button.classList.remove('bg-red-600')
            button.classList.add('bg-blue-600')
            message.textContent = 'Berbagi lokasi dihentikan di perangkat ini.'
            return
        }

        button.disabled = true
        message.textContent = 'Meminta izin lokasi dari browser…'
        try {
            await LocationAPI.getCurrentPosition()
            trackingEnabled = true
            button.textContent = 'Hentikan berbagi lokasi'
            button.classList.replace('bg-blue-600', 'bg-red-600')
            message.textContent = 'Lokasi dibagikan selama halaman ini terbuka. Anda dapat menghentikannya kapan saja.'
            trackingWatchId = LocationAPI.watchPosition(async (position) => {
                const now = Date.now()
                if (now - lastPositionSentAt < 10000) return
                lastPositionSentAt = now
                const point = [position.coords.latitude, position.coords.longitude]
                MapController.setVehicle(point)
                try {
                    await request(config.routes.position, {
                        method: 'POST',
                        body: JSON.stringify({
                            latitude: point[0],
                            longitude: point[1],
                            accuracy: position.coords.accuracy,
                        }),
                    })
                    await refreshTracking()
                } catch (error) {
                    message.textContent = `Lokasi belum terkirim: ${error.message}`
                }
            }, (error) => {
                message.textContent = `GPS tidak tersedia: ${error.message}`
                if (trackingEnabled) button.click()
            })
        } catch (error) {
            message.textContent = `Izin lokasi gagal: ${error.message}`
        } finally {
            button.disabled = false
        }
    })

    const makeSimulationPath = (employee) => {
        const origin = employee.location
            ? [employee.location.latitude, employee.location.longitude]
            : [-7.4243, 109.2302]
        const destination = employee.assignment
            ? [employee.assignment.latitude, employee.assignment.longitude]
            : [origin[0] + 0.005, origin[1] + 0.004]
        const steps = 20

        return Array.from({ length: steps }, (_, index) => {
            const progress = index / (steps - 1)
            const curve = Math.sin(progress * Math.PI) * 0.0015
            return [
                origin[0] + (destination[0] - origin[0]) * progress + curve,
                origin[1] + (destination[1] - origin[1]) * progress - curve,
            ]
        })
    }

    document.getElementById('toggle-simulation')?.addEventListener('click', async (event) => {
        const button = event.currentTarget
        const status = document.getElementById('simulation-status')
        if (simulationTimer) {
            clearInterval(simulationTimer)
            simulationTimer = null
            button.textContent = 'Mulai simulasi'
            button.classList.replace('bg-red-600', 'bg-gray-900')
            status.textContent = 'Simulasi dihentikan. Titik uji tetap terlihat di riwayat.'
            return
        }

        const id = elements.simulationEmployee.value
        const employee = employees.find((item) => String(item.id) === String(id))
        if (!employee) {
            status.textContent = 'Pilih karyawan untuk memulai simulasi.'
            return
        }

        selectEmployee(employee.id)
        simulationPath = makeSimulationPath(employee)
        simulationIndex = 0
        button.textContent = 'Hentikan simulasi'
        button.classList.replace('bg-gray-900', 'bg-red-600')
        status.textContent = `Mensimulasikan pergerakan ${employee.name} · setiap 3 detik`
        const sendSimulationPoint = async () => {
            if (simulationResetting) return
            const [latitude, longitude] = simulationPath[simulationIndex % simulationPath.length]
            simulationIndex += 1
            MapController.setVehicle([latitude, longitude], { isSimulated: true })
            try {
                await request(`${config.routes.simulationPrefix}${employee.id}`, {
                    method: 'POST',
                    body: JSON.stringify({ latitude, longitude }),
                })
                await refreshTracking()
            } catch (error) {
                status.textContent = `Simulasi gagal: ${error.message}`
                clearInterval(simulationTimer)
                simulationTimer = null
                button.textContent = 'Mulai simulasi'
                button.classList.replace('bg-red-600', 'bg-gray-900')
            }
        }
        const queueSimulationPoint = () => {
            if (simulationResetting || simulationSendPromise) return simulationSendPromise
            simulationSendPromise = sendSimulationPoint().finally(() => {
                simulationSendPromise = null
            })
            return simulationSendPromise
        }
        await queueSimulationPoint()
        if (!simulationResetting) simulationTimer = setInterval(queueSimulationPoint, 3000)
    })

    document.getElementById('reset-simulation')?.addEventListener('click', async (event) => {
        const button = event.currentTarget
        const status = document.getElementById('simulation-status')
        if (!window.confirm('Hapus seluruh riwayat dan titik GPS simulasi? Data GPS asli, karyawan, akun, dan tujuan tidak akan dihapus.')) return
        button.disabled = true
        simulationResetting = true
        if (simulationTimer) {
            clearInterval(simulationTimer)
            simulationTimer = null
        }
        const toggle = document.getElementById('toggle-simulation')
        toggle.disabled = true
        try {
            if (simulationSendPromise) await simulationSendPromise
            const result = await request(config.routes.simulationReset, { method: 'DELETE' })
            status.textContent = `${result.deleted} titik GPS simulasi dihapus. Data asli dan data master tetap aman.`
            await refreshTracking()
            if (!elements.journeyDialog.classList.contains('hidden')) await loadHistory(true)
        } catch (error) {
            status.textContent = `Reset simulasi gagal: ${error.message}`
        } finally {
            toggle.textContent = 'Mulai simulasi'
            toggle.classList.replace('bg-red-600', 'bg-gray-900')
            button.disabled = false
            toggle.disabled = false
            simulationPath = []
            simulationIndex = 0
            simulationResetting = false
        }
    })

    renderPlaces()
    renderEmployees()
    if (selectedEmployeeId) selectEmployee(selectedEmployeeId)
    else if (employees.length === 1 && !config.isManager) selectEmployee(employees[0].id)
    MapController.setAnnotations(places)
    refreshTracking()
    window.setInterval(() => refreshTracking(), 10000)
    window.addEventListener('beforeunload', () => {
        if (trackingWatchId !== null) navigator.geolocation.clearWatch(trackingWatchId)
        if (simulationTimer) clearInterval(simulationTimer)
        MapController.destroy()
    })
}
