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
        markerLayer: null,
        annotationLayer: null,
        vehicleMarker: null,
        clickHandler: null,
        markerSelectHandler: null,
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
            this.historyLayer = L.polyline([], { color: '#06b6d4', weight: 4, opacity: 0.9 }).addTo(this.instance)
            this.markerLayer = L.layerGroup().addTo(this.instance)
            this.annotationLayer = L.layerGroup().addTo(this.instance)
            this.instance.on('click', (event) => this.clickHandler?.([event.latlng.lat, event.latlng.lng]))
            this.instance.setView(options.center ?? [-7.4243, 109.2302], options.zoom ?? 14)

            return this.instance
        },
        destroy() {
            this.instance?.remove()
            this.instance = null
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
            const bounds = L.featureGroup([this.routeLayer, this.historyLayer, this.markerLayer, this.annotationLayer]).getBounds()
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
        assignmentMessage: document.getElementById('assignment-message'),
        mapStatus: document.getElementById('map-status'),
    }
    let employees = config.employees ?? []
    let places = config.places ?? []
    let selectedEmployeeId = config.isManager ? null : config.selfEmployeeId
    let targetPoint = null
    let targetMarker = null
    let trackingWatchId = null
    let simulationTimer = null
    let simulationIndex = 0
    let simulationPath = []
    let lastPositionSentAt = 0
    let trackingEnabled = false
    let placeSearchTimer = null
    let placeSearchRequest = 0
    let lastGeocodeRequestAt = 0
    let employeeSearchTerm = ''
    const geocodeCache = new Map()

    const getSelectedEmployee = () => employees.find((employee) => String(employee.id) === String(selectedEmployeeId))
    const formatTime = (date) => date ? new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date(date)) : '-'
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
        document.getElementById('assignment-count').textContent = employees.filter((employee) => employee.assignment).length
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
        MapController.setAnnotations(places)
        renderEmployeeDetail()
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
            <button type="button" id="show-history" class="mt-4 w-full rounded-lg bg-indigo-50 px-3 py-2.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Lihat riwayat perjalanan</button>`
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

    const loadHistory = async () => {
        const employee = getSelectedEmployee()
        MapController.setRoute([], { history: true })
        if (!employee) return
        elements.historyStatus.textContent = 'Memuat riwayat lokasi…'
        try {
            const response = await request(`${config.routes.historyPrefix}${employee.id}/history`)
            const points = response.points.map((point) => [point.latitude, point.longitude])
            MapController.setRoute(points, { history: true })
            elements.historyStatus.textContent = points.length
                ? `${points.length} titik riwayat ditampilkan · garis biru muda`
                : 'Belum ada riwayat lokasi untuk karyawan ini.'
        } catch (error) {
            elements.historyStatus.textContent = `Riwayat gagal dimuat: ${error.message}`
        }
    }

    const selectEmployee = (id, shouldLoadHistory = true) => {
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
        if (employee?.assignment) {
            setTarget({ ...employee.assignment, ephemeral: true })
        } else if (employee?.location) {
            MapController.panTo([employee.location.latitude, employee.location.longitude])
        }
        if (shouldLoadHistory) loadHistory()
    }

    const refreshTracking = async () => {
        try {
            const response = await request(config.routes.data)
            employees = response.employees
            renderEmployees()
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

    MapController.initialize('tracking-map', { center: [-7.4243, 109.2302], zoom: 14 })
    MapController.instance.invalidateSize()
    MapController.onMarkerSelect((id) => selectEmployee(id))
    MapController.onMapClick(async (coordinate) => {
        if (!config.isManager) return
        const point = { latitude: coordinate[0], longitude: coordinate[1], name: 'Titik pilihan di peta', ephemeral: true }
        setTarget(point)
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

    document.getElementById('show-history')?.addEventListener('click', loadHistory)
    elements.employeeSelect?.addEventListener('change', (event) => {
        selectEmployee(event.target.value || null)
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

    document.getElementById('assign-route')?.addEventListener('click', async () => {
        const employee = getSelectedEmployee()
        if (!employee || !targetPoint) {
            elements.assignmentMessage.textContent = 'Pilih karyawan dan tujuan terlebih dahulu.'
            return
        }
        const button = document.getElementById('assign-route')
        button.disabled = true
        try {
            const response = await request(config.routes.assignment, {
                method: 'POST',
                body: JSON.stringify({
                    employee_id: employee.id,
                    name: targetPoint.name,
                    latitude: targetPoint.latitude,
                    longitude: targetPoint.longitude,
                }),
            })
            employee.assignment = response.assignment
            elements.assignmentMessage.textContent = `Tujuan ${employee.name} diperbarui: ${response.assignment.name}`
            renderEmployees()
            await updateRoute()
        } catch (error) {
            elements.assignmentMessage.textContent = `Tujuan gagal disimpan: ${error.message}`
        } finally {
            button.disabled = false
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

        selectEmployee(employee.id, false)
        simulationPath = makeSimulationPath(employee)
        simulationIndex = 0
        button.textContent = 'Hentikan simulasi'
        button.classList.replace('bg-gray-900', 'bg-red-600')
        status.textContent = `Mensimulasikan pergerakan ${employee.name} · setiap 3 detik`
        const sendSimulationPoint = async () => {
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
        await sendSimulationPoint()
        simulationTimer = setInterval(sendSimulationPoint, 3000)
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
