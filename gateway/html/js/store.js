// ==============================================================================
// store.js - Lógica de Pedidos de Comida en Línea (Delivery)
// Delivery & Pickup, Menú de Autor, KDS Tracker, Checkout PCI-DSS y Mis Pedidos
// ==============================================================================

const Store = {
    products: [],
    categories: [],
    cart: [],
    currentCategory: null,
    searchQuery: '',
    dietaryFilter: null,
    orderMode: 'delivery', // 'delivery' | 'pickup'
    selectedBranch: 'Sucursal Zona 10 - Terraza Gourmet',
    tipAmount: 10.00,
    paymentMethod: 'stripe',
    activeOrder: null,

    init() {
        this.loadCart();
        this.loadOrderMode();
        this.bindEvents();
        this.fetchCategories();
        this.fetchProducts();
        this.checkAuthUI();
        this.checkLastOrder();
    },

    // 1. GESTIÓN DE MODALIDAD DELIVERY / PICKUP
    loadOrderMode() {
        const savedMode = localStorage.getItem('bistro_order_mode') || localStorage.getItem('delivery_order_mode');
        if (savedMode === 'delivery' || savedMode === 'pickup') {
            this.orderMode = savedMode;
        }
        const savedBranch = localStorage.getItem('bistro_selected_branch') || localStorage.getItem('delivery_selected_branch');
        if (savedBranch) {
            this.selectedBranch = savedBranch;
        }
        const savedAddr = localStorage.getItem('delivery_address');
        if (savedAddr) {
            const addrEl = document.getElementById('headerDeliveryAddress');
            if (addrEl) addrEl.textContent = savedAddr;
            const chkAddr = document.getElementById('chkDireccion');
            if (chkAddr) chkAddr.value = savedAddr;
        }
        this.updateModeUI();
    },

    openAddressModal() {
        const currentAddr = localStorage.getItem('delivery_address') || 'Zona 10, Ciudad de Guatemala';
        const newAddr = prompt('Ingresa tu dirección de entrega:', currentAddr);
        if (newAddr && newAddr.trim()) {
            localStorage.setItem('delivery_address', newAddr.trim());
            const addrEl = document.getElementById('headerDeliveryAddress');
            if (addrEl) addrEl.textContent = newAddr.trim();
            const chkAddr = document.getElementById('chkDireccion');
            if (chkAddr) chkAddr.value = newAddr.trim();
            this.showToastNotification('Dirección de entrega actualizada.', 'info');
        }
    },

    handleSearchInput(val) {
        this.searchQuery = (val || '').trim();
        this.fetchProducts();
    },

    setOrderMode(mode) {
        this.orderMode = mode;
        localStorage.setItem('delivery_order_mode', mode);
        localStorage.setItem('bistro_order_mode', mode);
        this.updateModeUI();
        this.renderCart();
    },

    setPickupBranch(branch) {
        this.selectedBranch = branch;
        localStorage.setItem('delivery_selected_branch', branch);
        localStorage.setItem('bistro_selected_branch', branch);
        const heroSel = document.getElementById('heroSucursalSelect');
        if (heroSel && heroSel.value !== branch) heroSel.value = branch;
        const chkSel = document.getElementById('chkSucursal');
        if (chkSel && chkSel.value !== branch) chkSel.value = branch;
    },

    updateModeUI() {
        const isDelivery = this.orderMode === 'delivery';

        // Botones Navbar
        const navDel = document.getElementById('navBtnDelivery');
        const navPic = document.getElementById('navBtnPickup');
        if (navDel) navDel.classList.toggle('active', isDelivery);
        if (navPic) navPic.classList.toggle('active', !isDelivery);

        // Botones Móvil
        const mobDel = document.getElementById('mobBtnDelivery');
        const mobPic = document.getElementById('mobBtnPickup');
        if (mobDel) mobDel.classList.toggle('active', isDelivery);
        if (mobPic) mobPic.classList.toggle('active', !isDelivery);

        // Botones Carrito
        const cartDel = document.getElementById('cartBtnDelivery');
        const cartPic = document.getElementById('cartBtnPickup');
        if (cartDel) cartDel.classList.toggle('active', isDelivery);
        if (cartPic) cartPic.classList.toggle('active', !isDelivery);

        // Hero Info
        const heroDelInfo = document.getElementById('heroDeliveryInfo');
        const heroPicInfo = document.getElementById('heroPickupInfo');
        const heroTitle = document.getElementById('heroModeTitle');
        if (heroDelInfo) heroDelInfo.style.display = isDelivery ? 'block' : 'none';
        if (heroPicInfo) heroPicInfo.style.display = isDelivery ? 'none' : 'block';
        if (heroTitle) {
            heroTitle.innerHTML = isDelivery 
                ? '<i class="ti ti-moped me-1 text-primary"></i> Modalidad: Delivery a Domicilio'
                : '<i class="ti ti-shopping-bag me-1 text-green"></i> Modalidad: Recojo en Restaurante (Pickup)';
        }

        // Checkout Fields
        const chkDel = document.getElementById('chkDeliveryFields');
        const chkPic = document.getElementById('chkPickupFields');
        if (chkDel) chkDel.style.display = isDelivery ? 'block' : 'none';
        if (chkPic) chkPic.style.display = isDelivery ? 'none' : 'block';

        // Cart notice
        const cartShipProg = document.getElementById('cartShippingProgressContainer');
        const cartNotice = document.getElementById('cartPickupNotice');
        if (cartShipProg) cartShipProg.style.display = isDelivery ? 'block' : 'none';
        if (cartNotice) cartNotice.style.display = isDelivery ? 'none' : 'block';

        const chkModeDisp = document.getElementById('checkoutModeDisplay');
        if (chkModeDisp) {
            chkModeDisp.textContent = isDelivery 
                ? 'Modalidad: Delivery a Domicilio (~35 min)'
                : `Modalidad: Pickup en ${this.selectedBranch}`;
        }
    },

    // 2. GESTIÓN DEL CARRITO
    loadCart() {
        try {
            const saved = localStorage.getItem('bistro_cart') || localStorage.getItem('technova_cart');
            this.cart = saved ? JSON.parse(saved) : [];
        } catch (e) {
            this.cart = [];
        }
        this.updateCartBadge();
    },

    saveCart() {
        localStorage.setItem('bistro_cart', JSON.stringify(this.cart));
        this.updateCartBadge();
    },

    updateCartBadge() {
        const count = this.cart.reduce((sum, item) => sum + item.cantidad, 0);
        const badge = document.getElementById('cartCount');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline-block' : 'none';
        }

        const subtotal = this.cart.reduce((sum, item) => sum + (parseFloat(item.precio) * item.cantidad), 0);
        const headerTotal = document.getElementById('cartTotalHeader');
        if (headerTotal) {
            headerTotal.textContent = `Q${subtotal.toFixed(2)}`;
        }

        // Actualizar barra flotante móvil
        const floatBar = document.getElementById('mobileFloatingCartBar');
        if (floatBar) {
            if (count > 0) {
                floatBar.style.display = 'block';
                const countEl = document.getElementById('mobileCartCount');
                const totalEl = document.getElementById('mobileCartTotal');
                if (countEl) countEl.textContent = `${count} ${count === 1 ? 'ítem' : 'ítems'}`;
                if (totalEl) totalEl.textContent = `Q${subtotal.toFixed(2)}`;
            } else {
                floatBar.style.display = 'none';
            }
        }
    },

    setTip(amount) {
        this.tipAmount = parseFloat(amount) || 0;
        [0, 10, 20, 30].forEach(val => {
            const btn = document.getElementById(`tipBtn${val}`);
            if (btn) {
                btn.classList.toggle('active', this.tipAmount === val);
            }
        });
        const cartPropina = document.getElementById('cartPropina');
        if (cartPropina) cartPropina.textContent = `Q${this.tipAmount.toFixed(2)}`;
        this.updateCartCalculations();
    },

    togglePaymentMethod(method) {
        this.paymentMethod = method;
    },

    // 3. CATEGORÍAS DEL MENÚ
    async fetchCategories() {
        try {
            const res = await API.getCategories();
            if (res.success && res.data) {
                this.categories = res.data;
                this.renderCategories();
            }
        } catch (err) {
            console.error('Error cargando categorías del menú:', err);
        }
    },

    renderCategories() {
        const container = document.getElementById('categoryChips');
        if (!container) return;

        const icons = {
            'entradas-y-tapas': 'ti-soup',
            'platos-fuertes-y-cortes': 'ti-meat',
            'pizzas-y-pastas': 'ti-pizza',
            'burgers-artesanales': 'ti-burger',
            'bowls-y-ensaladas': 'ti-salad',
            'postres-de-autor': 'ti-cake',
            'bebidas-y-cocteleria': 'ti-glass-cocktail'
        };

        let html = `
            <button class="cat-pill-btn ${!this.currentCategory ? 'active' : ''}" onclick="Store.filterCategory(null)">
                <i class="ti ti-tools-kitchen-2"></i> <span>Todo el Menú</span>
            </button>
        `;

        this.categories.forEach(cat => {
            const iconClass = icons[cat.slug] || 'ti-tools-kitchen';
            const isActive = this.currentCategory === cat.id;
            html += `
                <button class="cat-pill-btn ${isActive ? 'active' : ''}" onclick="Store.filterCategory(${cat.id})">
                    <i class="ti ${iconClass}"></i> <span>${cat.nombre}</span>
                </button>
            `;
        });

        container.innerHTML = html;
    },

    filterCategory(catId) {
        this.currentCategory = catId;
        this.renderCategories();
        this.fetchProducts();

        const cat = this.categories.find(c => c.id === catId);
        const titleEl = document.getElementById('catalogTitle');
        if (titleEl) {
            titleEl.textContent = cat ? cat.nombre : 'Menú de Platos';
        }
    },

    toggleDietaryFilter(type) {
        if (this.dietaryFilter === type) {
            this.dietaryFilter = null;
        } else {
            this.dietaryFilter = type;
        }

        const btnChef = document.getElementById('btnFilterChef');
        const btnVeg = document.getElementById('btnFilterVegetarian');
        if (btnChef) btnChef.classList.toggle('active', this.dietaryFilter === 'chef');
        if (btnVeg) btnVeg.classList.toggle('active', this.dietaryFilter === 'vegetariano');

        this.renderProducts();
    },

    // 4. PLATILLOS Y PRODUCTOS DEL CATÁLOGO
    async fetchProducts() {
        const grid = document.getElementById('productsGrid');
        if (grid) {
            grid.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-secondary mt-2 small">Preparando menú gastronómico desde MariaDB Slave...</div>
                </div>
            `;
        }

        try {
            const params = {};
            if (this.currentCategory) params.categoria_id = this.currentCategory;
            if (this.searchQuery) params.q = this.searchQuery;

            const res = await API.getProducts(params);
            if (res.success && res.data) {
                this.products = res.data;
                this.renderProducts();
            }
        } catch (err) {
            if (grid) {
                grid.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger" role="alert">
                            <i class="ti ti-alert-triangle me-2"></i> Error al conectar con el microservicio de menú gastronómico.
                        </div>
                    </div>
                `;
            }
        }
    },

    renderProducts() {
        const grid = document.getElementById('productsGrid');
        const badgeCount = document.getElementById('showingCountBadge');
        if (!grid) return;

        let filtered = this.products;
        if (this.dietaryFilter === 'chef') {
            filtered = filtered.filter(p => p.destacado == 1);
        } else if (this.dietaryFilter === 'vegetariano') {
            filtered = filtered.filter(p => {
                const desc = (p.descripcion || '').toLowerCase();
                const nom = (p.nombre || '').toLowerCase();
                return desc.includes('vegano') || desc.includes('ensalada') || desc.includes('quinoa') || nom.includes('bowl');
            });
        }

        if (badgeCount) {
            badgeCount.textContent = `${filtered.length} platillos disponibles`;
        }

        if (filtered.length === 0) {
            grid.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="avatar avatar-xl bg-light text-secondary rounded-circle mx-auto mb-3">
                        <i class="ti ti-soup-off fs-1"></i>
                    </div>
                    <h3 class="text-dark">No se encontraron platillos</h3>
                    <p class="text-secondary small">Prueba buscar con otro término o seleccionando otra categoría.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = filtered.map(prod => {
            const porciones = parseInt(prod.porciones_disponibles ?? prod.stock);
            const inStock = porciones > 0;
            const stockBadge = inStock 
                ? `<span class="badge-portion-pill text-green"><i class="ti ti-circle-check-filled text-green"></i> ${porciones} disponibles</span>`
                : (prod.insumos_faltantes && prod.insumos_faltantes.length 
                    ? `<span class="badge-portion-pill text-danger" title="Insumos faltantes: ${prod.insumos_faltantes.join(', ')}"><i class="ti ti-alert-triangle text-danger"></i> ${prod.insumos_faltantes.some(n => /vencido/i.test(n)) ? 'Insumo vencido' : 'Agotado'}</span>`
                    : `<span class="badge-portion-pill text-danger"><i class="ti ti-ban text-danger"></i> Agotado</span>`);

            const ribbonHtml = prod.destacado == 1 
                ? `<div class="ribbon bg-primary text-white fw-bold shadow-xs"><i class="ti ti-star me-1"></i> Favorito</div>` 
                : '';

            // Parse specs
            let prepTime = '15-20 min';
            let calories = '';
            let ingredientsShort = '';
            if (prod.especificaciones && typeof prod.especificaciones === 'object') {
                prepTime = prod.especificaciones.tiempo_preparacion || prepTime;
                calories = prod.especificaciones.calorias || '';
                if (prod.especificaciones.ingredientes && Array.isArray(prod.especificaciones.ingredientes)) {
                    ingredientsShort = prod.especificaciones.ingredientes.slice(0, 3).join(', ');
                }
            }

            const inCartItem = this.cart.find(it => it.id === prod.id);
            const cartQty = inCartItem ? inCartItem.cantidad : 0;

            return `
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <div class="card-dish-modern h-100 ${!inStock ? 'opacity-75' : ''}">
                        ${ribbonHtml}
                        <div class="dish-media" onclick="Store.openQuickView(${prod.id})" style="cursor: pointer;" title="Ver detalle del plato">
                            <img src="${prod.imagen_url}" alt="${prod.nombre}" loading="lazy"/>
                            <div class="position-absolute top-0 start-0 m-2">
                                <span class="badge-prep"><i class="ti ti-clock text-primary"></i> ${prepTime}</span>
                            </div>
                            <div class="position-absolute bottom-0 end-0 m-2">
                                ${stockBadge}
                            </div>
                            ${cartQty > 0 ? `
                                <div class="position-absolute bottom-0 start-0 m-2">
                                    <span class="badge bg-primary text-white rounded-pill px-2 py-1 shadow-sm"><i class="ti ti-check me-1"></i>${cartQty} en tu orden</span>
                                </div>
                            ` : ''}
                        </div>
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-light text-primary text-uppercase fw-bold" style="font-size: 0.68rem;">${prod.categoria_nombre}</span>
                                ${calories ? `<span class="badge bg-light text-muted" style="font-size: 0.68rem;"><i class="ti ti-flame me-1 text-danger"></i>${calories}</span>` : ''}
                            </div>
                            <h3 class="card-title text-dark mb-1 fw-bold text-truncate" style="font-size: 1.05rem; cursor: pointer;" onclick="Store.openQuickView(${prod.id})" title="${prod.nombre}">
                                ${prod.nombre}
                            </h3>
                            <p class="text-secondary small mb-2 text-truncate-2" style="min-height: 38px; line-height: 1.4;">
                                ${prod.descripcion}
                            </p>
                            ${ingredientsShort ? `
                                <div class="text-muted small mb-3 text-truncate" style="font-size: 0.72rem;">
                                    <i class="ti ti-tools-kitchen me-1 text-secondary"></i> ${ingredientsShort}...
                                </div>
                            ` : '<div class="mb-3"></div>'}
                            <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-bold" style="font-size: 0.65rem;">PRECIO</div>
                                    <div class="h2 mb-0 font-monospace text-dark fw-bold">Q${parseFloat(prod.precio).toFixed(2)}</div>
                                </div>
                                <div class="d-flex gap-1">
                                    <button 
                                        class="btn btn-outline-secondary btn-sm p-1 px-2 rounded-circle"
                                        title="Ver detalles"
                                        onclick="Store.openQuickView(${prod.id})"
                                    >
                                        <i class="ti ti-eye"></i>
                                    </button>
                                    <button 
                                        class="btn ${inStock ? 'btn-primary' : 'btn-secondary'} btn-sm d-inline-flex align-items-center rounded-pill px-3 fw-bold shadow-xs"
                                        onclick="Store.addToCart(${prod.id})" 
                                        ${!inStock ? 'disabled' : ''}
                                    >
                                        <i class="ti ${inStock ? 'ti-plus' : 'ti-ban'} me-1"></i> ${inStock ? 'Añadir' : 'Agotado'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    addToCart(prodId, note = '') {
        const prod = this.products.find(p => p.id === prodId);
        if (!prod) return;

        const availableStock = parseInt(prod.porciones_disponibles ?? prod.stock);
        if (availableStock <= 0) {
            this.showToastNotification(`Lo sentimos, "${prod.nombre}" no está disponible por falta de insumos en cocina.`, 'danger');
            return;
        }

        const existing = this.cart.find(item => item.id === prodId);
        if (existing) {
            if (existing.cantidad + 1 > availableStock) {
                this.showToastNotification(`Solo hay ${availableStock} porciones disponibles de "${prod.nombre}" hoy.`, 'warning');
                return;
            }
            existing.cantidad += 1;
            if (note) existing.nota = note;
        } else {
            this.cart.push({
                id: prod.id,
                sku: prod.sku,
                nombre: prod.nombre,
                precio: parseFloat(prod.precio),
                imagen_url: prod.imagen_url,
                cantidad: 1,
                nota: note
            });
        }

        this.saveCart();
        this.showToastNotification(`¡"${prod.nombre}" añadido a tu orden!`);
    },

    showToastNotification(msg, tipo = 'success') {
        const toast = document.createElement('div');
        const borderColor = tipo === 'danger' ? 'border-danger' : (tipo === 'warning' ? 'border-warning' : 'border-primary');
        const iconColor = tipo === 'danger' ? 'text-danger ti-circle-x' : (tipo === 'warning' ? 'text-warning ti-alert-triangle' : 'text-green ti-circle-check');
        toast.className = `position-fixed bottom-0 end-0 m-3 p-3 bg-white border ${borderColor} rounded shadow-lg d-flex align-items-center gap-2`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `<i class="ti ${iconColor} fs-2"></i> <span class="small fw-bold text-dark">${msg}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    },

    changeCartQuantity(prodId, delta) {
        const item = this.cart.find(it => it.id === prodId);
        if (!item) return;

        if (delta > 0) {
            const prod = this.products.find(p => p.id === prodId);
            const availableStock = prod ? parseInt(prod.porciones_disponibles ?? prod.stock) : 999;
            if (item.cantidad + delta > availableStock) {
                this.showToastNotification(`Solo hay ${availableStock} porciones preparables de este platillo hoy.`, 'warning');
                return;
            }
        }

        item.cantidad += delta;
        if (item.cantidad <= 0) {
            this.cart = this.cart.filter(it => it.id !== prodId);
        }
        this.saveCart();
        this.renderCart();
    },

    // 5. RENDERIZADO Y MODAL DEL CARRITO
    openCart() {
        this.updateModeUI();
        this.renderCart();
        const modal = new bootstrap.Modal(document.getElementById('cartModal'));
        modal.show();
    },

    renderCart() {
        const container = document.getElementById('cartItemsList');
        if (!container) return;

        if (this.cart.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="avatar avatar-lg bg-light text-secondary rounded-circle mx-auto mb-2">
                        <i class="ti ti-shopping-bag-off fs-1"></i>
                    </div>
                    <h4 class="text-dark">Tu orden está vacía</h4>
                    <p class="text-muted small">Explora nuestros platillos y selecciona lo que deseas degustar.</p>
                </div>
            `;
            this.updateCartCalculations();
            return;
        }

        container.innerHTML = this.cart.map(item => `
            <div class="list-group-item px-0 py-2 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <img src="${item.imagen_url}" alt="${item.nombre}" class="rounded" style="width: 54px; height: 54px; object-fit: cover;"/>
                    <div class="flex-grow-1">
                        <h4 class="mb-0 text-dark small fw-bold" style="font-family: 'Playfair Display', serif;">${item.nombre}</h4>
                        <div class="text-muted font-monospace small">Q${item.precio.toFixed(2)} c/u</div>
                        ${item.nota ? `<small class="text-primary fst-italic"><i class="ti ti-notes me-1"></i>${item.nota}</small>` : ''}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-secondary btn-icon btn-sm" onclick="Store.changeCartQuantity(${item.id}, -1)">-</button>
                        <span class="fw-bold font-monospace px-1">${item.cantidad}</span>
                        <button class="btn btn-outline-secondary btn-icon btn-sm" onclick="Store.changeCartQuantity(${item.id}, 1)">+</button>
                    </div>
                    <div class="text-end font-monospace fw-bold text-dark" style="min-width: 60px;">
                        Q${(item.precio * item.cantidad).toFixed(2)}
                    </div>
                </div>
            </div>
        `).join('');

        this.updateCartCalculations();
    },

    updateCartCalculations() {
        const subtotal = this.cart.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        const iva = subtotal * 0.12;

        // Reglas de negocio Delivery vs Pickup:
        // Pickup siempre tiene Q0.00 de envío.
        // Delivery: Gratis si subtotal >= Q150.00, sino Q15.00.
        const threshold = 150.00;
        let envio = 0.00;
        if (this.orderMode === 'delivery') {
            envio = subtotal >= threshold ? 0.00 : (subtotal > 0 ? 15.00 : 0.00);
        }

        const total = subtotal + iva + envio + (subtotal > 0 ? this.tipAmount : 0);

        // Barra de Envío Gratis para Delivery
        const progBar = document.getElementById('shippingProgressBar');
        const progText = document.getElementById('shippingProgressText');
        const progPct = document.getElementById('shippingPercentText');
        if (progBar && progText && progPct) {
            const pct = Math.min(100, Math.round((subtotal / threshold) * 100));
            progBar.style.width = `${pct}%`;
            progPct.textContent = `${pct}%`;
            if (subtotal >= threshold) {
                progText.innerHTML = '<span class="text-green fw-bold"><i class="ti ti-check me-1"></i> ¡Calificas para Envío Gratis!</span>';
                progBar.className = 'progress-bar bg-green';
            } else {
                const rest = (threshold - subtotal).toFixed(2);
                progText.textContent = `Agrega Q${rest} para envío gratis`;
                progBar.className = 'progress-bar bg-primary';
            }
        }

        // Elementos numéricos
        const subEl = document.getElementById('cartSubtotal');
        const ivaEl = document.getElementById('cartIva');
        const envEl = document.getElementById('cartEnvio');
        const envLabel = document.getElementById('cartEnvioLabel');
        const totEl = document.getElementById('cartTotal');

        if (subEl) subEl.textContent = `Q${subtotal.toFixed(2)}`;
        if (ivaEl) ivaEl.textContent = `Q${iva.toFixed(2)}`;
        if (envEl) {
            if (this.orderMode === 'pickup') {
                envEl.textContent = 'GRATIS (Q0.00)';
                if (envLabel) envLabel.textContent = 'Recojo en Sucursal:';
            } else {
                envEl.textContent = envio === 0 ? 'GRATIS' : `Q${envio.toFixed(2)}`;
                if (envLabel) envLabel.textContent = 'Costo de Envío:';
            }
        }
        if (totEl) totEl.textContent = `Q${total.toFixed(2)}`;
    },

    // 6. PROCESO DE CHECKOUT SEGURO STRIPE (PCI-DSS)
    startCheckout() {
        if (this.cart.length === 0) {
            alert('Agrega al menos un platillo a tu orden para continuar.');
            return;
        }

        // Si no está autenticado, solicitar inicio de sesión
        const user = API.getUser();
        if (!user) {
            bootstrap.Modal.getInstance(document.getElementById('cartModal'))?.hide();
            this.openAuthModal('login');
            return;
        }

        bootstrap.Modal.getInstance(document.getElementById('cartModal'))?.hide();

        // Calcular total para el modal de checkout
        const subtotal = this.cart.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        const iva = subtotal * 0.12;
        const envio = this.orderMode === 'pickup' ? 0.00 : (subtotal >= 150.00 ? 0.00 : 15.00);
        const total = subtotal + iva + envio + this.tipAmount;

        const totDisp = document.getElementById('checkoutTotalDisplay');
        if (totDisp) totDisp.textContent = `Q${total.toFixed(2)} GTQ`;

        this.updateModeUI();
        const chkModal = new bootstrap.Modal(document.getElementById('checkoutModal'));
        chkModal.show();
    },

    async processCheckoutSubmit(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitPayment');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando a Cocina & Procesando...';
        }

        try {
            const direccion = this.orderMode === 'delivery' 
                ? (document.getElementById('chkDireccion')?.value || '12 Calle 4-55 Zona 10')
                : `Recojo en ${this.selectedBranch}`;
            const telefono = document.getElementById('chkTelefono')?.value || '+502 5544-3322';
            const notas = document.getElementById('cartKitchenNotes')?.value || '';
            const simularFallo = document.getElementById('chkSimularFallo')?.checked || false;

            const itemsPayload = this.cart.map(it => ({
                id: it.id,
                cantidad: it.cantidad,
                nota: it.nota || ''
            }));

            // 1. Crear Pedido en pedidos-service (OWASP Tampering Prevention: precios tomados del servidor)
            const orderRes = await API.createOrder({
                items: itemsPayload,
                direccion,
                telefono,
                notas,
                tipo_entrega: this.orderMode,
                sucursal: this.selectedBranch,
                propina: this.tipAmount
            });

            if (!orderRes.success || !orderRes.pedido) {
                throw new Error(orderRes.error || 'Error al registrar pedido');
            }

            const pedido = orderRes.pedido;

            // 2. Si el método es Stripe, iniciar y confirmar pago tokenizado
            let paymentIntentId = 'pi_cash_on_delivery_' + Math.random().toString(36).substring(7);
            if (this.paymentMethod === 'stripe') {
                const initPay = await API.initPayment(pedido.id);
                paymentIntentId = initPay.payment_intent_id || ('pi_3NzBistroTest_' + Math.random().toString(36).substring(7));

                const confPay = await API.confirmPayment(pedido.id, paymentIntentId, simularFallo);
                if (!confPay.success) {
                    throw new Error(confPay.error || 'Pago rechazado por entidad emisora');
                }
            }

            // Guardar pedido activo para el rastreador en vivo
            this.activeOrder = {
                id: pedido.id,
                numero_pedido: pedido.numero_pedido,
                tipo_entrega: this.orderMode,
                sucursal: this.selectedBranch,
                direccion: direccion,
                total: pedido.total,
                estado: 'recibido',
                items: pedido.items || this.cart
            };
            localStorage.setItem('bistro_last_order', JSON.stringify(this.activeOrder));

            // Limpiar carrito
            this.cart = [];
            this.saveCart();

            // Cerrar checkout modal y mostrar recibo
            bootstrap.Modal.getInstance(document.getElementById('checkoutModal'))?.hide();

            const ordNum = document.getElementById('successOrderNum');
            const ordTipo = document.getElementById('successTipoEntrega');
            const payIntent = document.getElementById('successPaymentIntent');
            const succTot = document.getElementById('successTotal');

            if (ordNum) ordNum.textContent = pedido.numero_pedido;
            if (ordTipo) ordTipo.textContent = this.orderMode === 'delivery' ? 'Delivery a Domicilio' : `Pickup en ${this.selectedBranch}`;
            if (payIntent) payIntent.textContent = paymentIntentId;
            if (succTot) succTot.textContent = `Q${parseFloat(pedido.total).toFixed(2)}`;

            const succModal = new bootstrap.Modal(document.getElementById('successModal'));
            succModal.show();

        } catch (err) {
            alert('Atención en el Checkout:\n\n' + err.message);
            this.fetchProducts(); // Actualiza disponibilidad en vivo
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ti ti-lock me-1"></i> Confirmar y Enviar Pedido a Cocina';
            }
        }
    },

    // 7. RASTREADOR EN VIVO DE ORDEN (KDS TRACKER CLIENTE)
    checkLastOrder() {
        try {
            const saved = localStorage.getItem('bistro_last_order');
            if (saved) this.activeOrder = JSON.parse(saved);
        } catch (e) {}
    },

    async openActiveOrderTracker() {
        if (!this.activeOrder) {
            alert('Aún no tienes ningún pedido activo en curso. ¡Explora nuestro menú y realiza tu orden!');
            return;
        }

        // Intentar consultar estado más fresco de la base de datos
        try {
            const res = await API.getOrderById(this.activeOrder.id);
            if (res.success && res.data) {
                this.activeOrder.estado = res.data.estado;
                this.activeOrder.tipo_entrega = res.data.tipo_entrega || this.activeOrder.tipo_entrega;
            }
        } catch (e) {}

        this.renderTrackerModal();
        const trackerModal = new bootstrap.Modal(document.getElementById('trackerModal'));
        trackerModal.show();
    },

    renderTrackerModal() {
        if (!this.activeOrder) return;

        const ordNum = document.getElementById('trackOrderNum');
        const badgeStatus = document.getElementById('trackBadgeStatus');
        const destinoLabel = document.getElementById('trackDestinoLabel');
        const destinoText = document.getElementById('trackDestinoText');
        const estTime = document.getElementById('trackEstimatedTime');
        const itemsList = document.getElementById('trackItemsList');
        const step3Icon = document.getElementById('step3Icon');
        const step3Label = document.getElementById('step3Label');

        if (ordNum) ordNum.textContent = this.activeOrder.numero_pedido;

        const isDelivery = this.activeOrder.tipo_entrega === 'delivery';
        if (destinoLabel) destinoLabel.textContent = isDelivery ? 'Dirección de Entrega:' : 'Punto de Recojo (Pickup):';
        if (destinoText) destinoText.textContent = this.activeOrder.direccion || this.activeOrder.sucursal;

        if (step3Icon) {
            step3Icon.innerHTML = isDelivery ? '<i class="ti ti-moped"></i>' : '<i class="ti ti-shopping-bag"></i>';
        }
        if (step3Label) {
            step3Label.textContent = isDelivery ? 'En Camino' : 'Listo para Recojo';
        }

        // Mapeo de Estados
        const estado = this.activeOrder.estado || 'recibido';
        const s1 = document.getElementById('step1');
        const s2 = document.getElementById('step2');
        const s3 = document.getElementById('step3');
        const s4 = document.getElementById('step4');

        [s1, s2, s3, s4].forEach(s => s && (s.className = 'step-item'));

        if (estado === 'recibido') {
            if (s1) s1.className = 'step-item active';
            if (badgeStatus) badgeStatus.textContent = '1. Recibido por Cocina';
            if (estTime) estTime.textContent = isDelivery ? '30 - 40 minutos' : '15 - 20 minutos';
        } else if (estado === 'en_preparacion' || estado === 'pagado') {
            if (s1) s1.className = 'step-item completed';
            if (s2) s2.className = 'step-item active';
            if (badgeStatus) badgeStatus.textContent = '2. En Cocina / Fuego Lento';
            if (estTime) estTime.textContent = isDelivery ? '20 - 25 minutos' : '10 - 15 minutos';
        } else if (estado === 'listo' || estado === 'en_camino') {
            if (s1) s1.className = 'step-item completed';
            if (s2) s2.className = 'step-item completed';
            if (s3) s3.className = 'step-item active';
            if (badgeStatus) badgeStatus.textContent = isDelivery ? '3. Repartidor en Camino' : '3. Listo en Mostrador para Recoger';
            if (estTime) estTime.textContent = isDelivery ? '5 - 10 minutos' : '¡Listo para entrega inmediata!';
        } else if (estado === 'entregado') {
            if (s1) s1.className = 'step-item completed';
            if (s2) s2.className = 'step-item completed';
            if (s3) s3.className = 'step-item completed';
            if (s4) s4.className = 'step-item completed';
            if (badgeStatus) badgeStatus.textContent = '4. Entregado / ¡Buen Provecho!';
            if (estTime) estTime.textContent = 'Completado con éxito.';
        }

        // Lista de Platillos
        if (itemsList && this.activeOrder.items) {
            itemsList.innerHTML = this.activeOrder.items.map(it => `
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span>${it.cantidad || 1}x ${it.nombre_producto || it.nombre}</span>
                    <span class="text-muted font-monospace">Q${parseFloat(it.subtotal || (it.precio * (it.cantidad || 1))).toFixed(2)}</span>
                </div>
            `).join('');
        }
    },

    // 8. VISTA RÁPIDA DE PLATILLO (QUICK VIEW)
    openQuickView(prodId) {
        const prod = this.products.find(p => p.id === prodId);
        if (!prod) return;

        const qvTitle = document.getElementById('qvTitle');
        const qvName = document.getElementById('qvName');
        const qvDesc = document.getElementById('qvDesc');
        const qvPrice = document.getElementById('qvPrice');
        const qvImage = document.getElementById('qvImage');
        const qvCategory = document.getElementById('qvCategory');
        const qvPrepBadge = document.getElementById('qvPrepBadge');
        const qvSpecs = document.getElementById('qvSpecsTable');
        const qvAddBtn = document.getElementById('qvAddBtn');

        if (qvTitle) qvTitle.textContent = prod.nombre;
        if (qvName) qvName.textContent = prod.nombre;
        if (qvDesc) qvDesc.textContent = prod.descripcion;
        if (qvPrice) qvPrice.textContent = `Q${parseFloat(prod.precio).toFixed(2)}`;
        if (qvImage) {
            qvImage.src = prod.imagen_url;
            qvImage.alt = prod.nombre;
        }
        if (qvCategory) qvCategory.textContent = prod.categoria_nombre || 'Especialidad';

        const porciones = parseInt(prod.porciones_disponibles ?? prod.stock);
        const inStock = porciones > 0;

        let prepTime = '15-20 min';
        let specsHtml = '';

        if (!inStock) {
            const faltantes = (prod.insumos_faltantes && prod.insumos_faltantes.length) 
                ? prod.insumos_faltantes.join(', ') 
                : 'Materia prima no disponible';
            const hayVencido = /vencido/i.test(faltantes);
            specsHtml += `
                <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-2">
                    <i class="ti ti-alert-triangle me-2 fs-2"></i>
                    <div>
                        <strong>Platillo no disponible:</strong> ${hayVencido ? 'Un ingrediente venció y no se puede cocinar hasta reponerlo.' : 'No contamos con ingredientes o stock suficiente en cocina para prepararlo hoy.'}
                        <div class="text-muted mt-1 small">${hayVencido ? 'Insumo vencido' : 'Insumo agotado'}: <strong>${faltantes}</strong></div>
                    </div>
                </div>
            `;
        } else {
            specsHtml += `
                <div class="alert alert-success py-2 px-3 small d-flex align-items-center mb-2">
                    <i class="ti ti-circle-check me-2 fs-2 text-green"></i>
                    <div>
                        <strong>Disponible en cocina:</strong> Contamos con insumos para preparar <strong>${porciones} porciones</strong> frescas hoy.
                    </div>
                </div>
            `;
        }

        if (prod.especificaciones && typeof prod.especificaciones === 'object') {
            prepTime = prod.especificaciones.tiempo_preparacion || prepTime;
            const alergenos = prod.especificaciones.alergenos || [];
            const ingredientes = prod.especificaciones.ingredientes || [];

            specsHtml += `
                <div class="p-2 bg-light rounded small mb-2 border">
                    <div class="mb-1"><strong><i class="ti ti-flame text-danger me-1"></i>Calorías:</strong> ${prod.especificaciones.calorias || 'Aprox 550 kcal'}</div>
                    <div class="mb-1"><strong><i class="ti ti-users text-primary me-1"></i>Porción:</strong> ${prod.especificaciones.porciones || '1 persona'}</div>
                    ${ingredientes.length ? `<div class="mb-1"><strong><i class="ti ti-salad text-green me-1"></i>Ingredientes:</strong> ${ingredientes.join(', ')}</div>` : ''}
                    ${alergenos.length ? `<div class="text-danger"><strong><i class="ti ti-alert-triangle me-1"></i>Alérgenos:</strong> ${alergenos.join(', ')}</div>` : ''}
                </div>
            `;
        }

        if (qvPrepBadge) {
            qvPrepBadge.innerHTML = `<span class="badge-prep border"><i class="ti ti-clock text-primary"></i> Tiempo estimado de cocina: <strong>${prepTime}</strong></span>`;
        }
        if (qvSpecs) qvSpecs.innerHTML = specsHtml;

        if (qvAddBtn) {
            if (inStock) {
                qvAddBtn.disabled = false;
                qvAddBtn.className = 'btn btn-primary';
                qvAddBtn.innerHTML = '<i class="ti ti-shopping-bag-plus me-1"></i> Añadir a mi Orden';
                qvAddBtn.onclick = () => {
                    const note = document.getElementById('qvItemNote')?.value || '';
                    Store.addToCart(prod.id, note);
                    bootstrap.Modal.getInstance(document.getElementById('quickViewModal'))?.hide();
                };
            } else {
                qvAddBtn.disabled = true;
                qvAddBtn.className = 'btn btn-secondary';
                qvAddBtn.innerHTML = '<i class="ti ti-ban me-1"></i> Agotado por hoy';
            }
        }

        const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
        modal.show();
    },

    // 9. AUTENTICACIÓN Y MIS PEDIDOS
    checkAuthUI() {
        const user = API.getUser();
        const userMenu = document.getElementById('userMenuDropdown');
        if (!userMenu) return;

        if (user) {
            userMenu.innerHTML = `
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        <i class="ti ti-user-check text-green me-1"></i> ${user.nombre.split(' ')[0]}
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                        <div class="dropdown-header">Conectado como ${user.email}</div>
                        <a class="dropdown-item" href="#" onclick="Store.openOrdersModal(); return false;">
                            <i class="ti ti-receipt me-2"></i> Mis Pedidos
                        </a>
                        ${['admin', 'cocina', 'auditor', 'operador'].includes(user.rol) ? `
                            <a class="dropdown-item text-primary" href="/admin.html">
                                <i class="ti ti-chef-hat me-2"></i> Consola Cocina & Admin
                            </a>
                        ` : ''}
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="#" onclick="Store.logout(); return false;">
                            <i class="ti ti-logout me-2"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
            `;
        } else {
            userMenu.innerHTML = `
                <button class="btn btn-secondary btn-sm d-inline-flex align-items-center" onclick="Store.openAuthModal('login')">
                    <i class="ti ti-user me-1"></i> Ingresar
                </button>
            `;
        }
    },

    handleUserAuthClick() {
        const user = API.getUser();
        if (user) this.openOrdersModal();
        else this.openAuthModal('login');
    },

    openAuthModal(tab = 'login') {
        this.switchAuthTab(tab);
        const modal = new bootstrap.Modal(document.getElementById('authModal'));
        modal.show();
    },

    switchAuthTab(tab) {
        const loginForm = document.getElementById('loginForm');
        const regForm = document.getElementById('registerForm');
        const tabLoginBtn = document.getElementById('tabLoginBtn');
        const tabRegBtn = document.getElementById('tabRegisterBtn');

        if (tab === 'login') {
            if (loginForm) loginForm.style.display = 'block';
            if (regForm) regForm.style.display = 'none';
            if (tabLoginBtn) tabLoginBtn.classList.add('active');
            if (tabRegBtn) tabRegBtn.classList.remove('active');
        } else {
            if (loginForm) loginForm.style.display = 'none';
            if (regForm) regForm.style.display = 'block';
            if (tabLoginBtn) tabLoginBtn.classList.remove('active');
            if (tabRegBtn) tabRegBtn.classList.add('active');
        }
    },

    async handleLogin(e) {
        e.preventDefault();
        const email = document.getElementById('loginEmail')?.value;
        const pass = document.getElementById('loginPassword')?.value;
        const errEl = document.getElementById('loginError');

        try {
            if (errEl) errEl.textContent = '';
            await API.login(email, pass);
            bootstrap.Modal.getInstance(document.getElementById('authModal'))?.hide();
            this.checkAuthUI();
            this.showToastNotification('¡Sesión iniciada con éxito!');
        } catch (err) {
            if (errEl) errEl.textContent = err.message || 'Credenciales incorrectas';
        }
    },

    async handleRegister(e) {
        e.preventDefault();
        const nom = document.getElementById('regNombre')?.value;
        const email = document.getElementById('regEmail')?.value;
        const pass = document.getElementById('regPassword')?.value;
        const tel = document.getElementById('regTelefono')?.value || '';
        const dir = document.getElementById('regDireccion')?.value || '';
        const errEl = document.getElementById('regError');

        try {
            if (errEl) errEl.textContent = '';
            await API.register(nom, email, pass, tel, dir);
            bootstrap.Modal.getInstance(document.getElementById('authModal'))?.hide();
            this.checkAuthUI();
            this.showToastNotification('¡Cuenta registrada exitosamente!');
        } catch (err) {
            if (errEl) errEl.textContent = err.message || 'Error al crear cuenta';
        }
    },

    async logout() {
        await API.logout();
        this.checkAuthUI();
        this.showToastNotification('Sesión finalizada.');
    },

    async openOrdersModal() {
        const user = API.getUser();
        if (!user) {
            this.openAuthModal('login');
            return;
        }

        const table = document.getElementById('customerOrdersTable');
        if (table) {
            table.innerHTML = `<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div> Cargando historial de pedidos...</td></tr>`;
        }

        const modal = new bootstrap.Modal(document.getElementById('ordersModal'));
        modal.show();

        try {
            const res = await API.getOrders();
            if (res.success && res.data) {
                if (res.data.length === 0) {
                    table.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">Aún no has realizado pedidos. ¡Explora el menú y pide hoy!</td></tr>`;
                    return;
                }

                table.innerHTML = res.data.map(ord => {
                    const statusBadges = {
                        'recibido': '<span class="badge bg-blue-lt">Recibido</span>',
                        'en_preparacion': '<span class="badge bg-amber-lt">En Cocina</span>',
                        'listo': '<span class="badge bg-purple-lt">Listo para Recoger</span>',
                        'en_camino': '<span class="badge bg-cyan-lt">En Camino</span>',
                        'entregado': '<span class="badge bg-green-lt">Entregado</span>',
                        'cancelado': '<span class="badge bg-red-lt">Cancelado</span>'
                    };
                    const badge = statusBadges[ord.estado] || `<span class="badge bg-secondary-lt">${ord.estado}</span>`;
                    const tipoText = ord.tipo_entrega === 'pickup' 
                        ? '<span class="badge bg-purple-lt"><i class="ti ti-shopping-bag me-1"></i>Pickup</span>' 
                        : '<span class="badge bg-cyan-lt"><i class="ti ti-moped me-1"></i>Delivery</span>';

                    return `
                        <tr>
                            <td class="font-monospace fw-bold">${ord.numero_pedido}</td>
                            <td>${tipoText}</td>
                            <td class="small text-muted">${API.formatDate(ord.fecha)}</td>
                            <td class="font-monospace text-primary fw-bold">Q${parseFloat(ord.total).toFixed(2)}</td>
                            <td>${badge}</td>
                            <td>
                                <button class="btn btn-outline-primary btn-sm py-0" onclick="Store.trackSpecificOrder(${ord.id})">
                                    <i class="ti ti-radar me-1"></i> Rastrear
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        } catch (err) {
            if (table) table.innerHTML = `<tr><td colspan="6" class="text-danger text-center">Error al consultar pedidos.</td></tr>`;
        }
    },

    async trackSpecificOrder(orderId) {
        bootstrap.Modal.getInstance(document.getElementById('ordersModal'))?.hide();
        try {
            const res = await API.getOrderById(orderId);
            if (res.success && res.data) {
                this.activeOrder = res.data;
                this.renderTrackerModal();
                new bootstrap.Modal(document.getElementById('trackerModal')).show();
            }
        } catch (e) {
            alert('No se pudo cargar el seguimiento del pedido');
        }
    },

    // 10. EVENTOS GLOBALES
    bindEvents() {
        const searchInput = document.getElementById('navSearchInput');
        if (searchInput) {
            let timeout = null;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.searchQuery = e.target.value.trim();
                    this.fetchProducts();
                }, 300);
            });
        }
    }
};

// Iniciar al cargar el DOM
document.addEventListener('DOMContentLoaded', () => Store.init());
