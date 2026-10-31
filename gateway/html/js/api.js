// ==============================================================================
// api.js - Cliente API Unificado para Gateway y Microservicios
// Bistró Gourmet - Restaurante, Delivery & Pickup
// Zero Trust: Inyección de Bearer JWT en todas las peticiones autenticadas
// ==============================================================================

const API = {
    getToken() {
        return localStorage.getItem('bistro_jwt_token') || localStorage.getItem('technova_jwt_token');
    },

    setToken(token) {
        if (token) {
            localStorage.setItem('bistro_jwt_token', token);
            localStorage.setItem('technova_jwt_token', token);
        } else {
            localStorage.removeItem('bistro_jwt_token');
            localStorage.removeItem('technova_jwt_token');
        }
    },

    getUser() {
        const u = localStorage.getItem('bistro_user') || localStorage.getItem('technova_user');
        return u ? JSON.parse(u) : null;
    },

    setUser(user) {
        if (user) {
            localStorage.setItem('bistro_user', JSON.stringify(user));
            localStorage.setItem('technova_user', JSON.stringify(user));
        } else {
            localStorage.removeItem('bistro_user');
            localStorage.removeItem('technova_user');
        }
    },

    async request(url, options = {}) {
        const headers = options.headers || {};
        if (!options._isFormData) {
            headers['Content-Type'] = 'application/json';
        }

        const token = this.getToken();
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        options.headers = headers;

        try {
            const res = await fetch(url, options);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.error || `Error HTTP ${res.status}`);
            }
            return data;
        } catch (err) {
            console.error(`[API ERROR] ${url}:`, err);
            throw err;
        }
    },

    // ============================================================
    // 1. AUTH SERVICE
    // ============================================================
    async login(email, password) {
        const res = await this.request('/api/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
        if (res.token) {
            this.setToken(res.token);
            this.setUser(res.user);
        }
        return res;
    },

    async register(nombre, email, password, telefono = '', direccion = '') {
        const res = await this.request('/api/auth/register', {
            method: 'POST',
            body: JSON.stringify({ nombre, email, password, telefono, direccion })
        });
        if (res.token) {
            this.setToken(res.token);
            this.setUser(res.user);
        }
        return res;
    },

    async logout() {
        try {
            await this.request('/api/auth/logout', { method: 'POST' });
        } catch (e) {}
        this.setToken(null);
        this.setUser(null);
    },

    async verifySession() {
        if (!this.getToken()) return null;
        try {
            const res = await this.request('/api/auth/verify');
            return res.user;
        } catch (e) {
            this.logout();
            return null;
        }
    },

    // Listado de usuarios (Solo Admin)
    async getUsers(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/auth/users${query ? '?' + query : ''}`);
    },

    // Cambiar estado de usuario (activar/desactivar)
    async updateUserStatus(id, activo) {
        return this.request(`/api/auth/users/${id}/status`, {
            method: 'PUT',
            body: JSON.stringify({ activo })
        });
    },

    // Cambiar rol de usuario
    async updateUserRole(id, rol) {
        return this.request(`/api/auth/users/${id}/rol`, {
            method: 'PUT',
            body: JSON.stringify({ rol })
        });
    },

    async exportUsers(formato = 'json') {
        if (formato === 'csv') {
            const token = this.getToken();
            window.location.href = `/api/auth/users/export?formato=csv&token=${token}`;
            return { success: true };
        }
        return this.request('/api/auth/users/export?formato=json');
    },

    // ============================================================
    // 2. CATALOGO SERVICE
    // ============================================================
    async getCategories() {
        return this.request('/api/catalogo/categorias');
    },

    async getProducts(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/catalogo/productos${query ? '?' + query : ''}`);
    },

    async getProduct(id) {
        return this.request(`/api/catalogo/productos/${id}`);
    },

    async updateProductPrice(id, precio, motivo) {
        return this.request(`/api/catalogo/productos/${id}/precio`, {
            method: 'PUT',
            body: JSON.stringify({ precio, motivo })
        });
    },

    async updateProductStock(id, stock, motivo = 'Ajuste manual de inventario') {
        return this.request(`/api/catalogo/productos/${id}/stock`, {
            method: 'PUT',
            body: JSON.stringify({ stock, motivo })
        });
    },

    async addProduct(data) {
        return this.request('/api/catalogo/productos', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async updateProduct(id, data) {
        return this.request(`/api/catalogo/productos/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    async deleteProduct(id) {
        return this.request(`/api/catalogo/productos/${id}`, {
            method: 'DELETE'
        });
    },

    // ============================================================
    // 2.1 INVENTARIO DE MATERIAS PRIMAS & RECETAS (BOM)
    // ============================================================
    async getRawMaterials(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/catalogo/materias-primas${query ? '?' + query : ''}`);
    },

    async getRawMaterial(id) {
        return this.request(`/api/catalogo/materias-primas/${id}`);
    },

    async addRawMaterial(data) {
        return this.request('/api/catalogo/materias-primas', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async updateRawMaterial(id, data) {
        return this.request(`/api/catalogo/materias-primas/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    async addRawMaterialMovement(id, data) {
        return this.request(`/api/catalogo/materias-primas/${id}/movimiento`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async getRawMaterialKardex(id, limit = 50) {
        return this.request(`/api/catalogo/materias-primas/${id}/kardex?limit=${limit}`);
    },

    async getInventoryMovements(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/catalogo/movimientos-inventario${query ? '?' + query : ''}`);
    },

    async getProductRecipe(id) {
        return this.request(`/api/catalogo/productos/${id}/receta`);
    },

    async saveRecipeIngredient(productId, data) {
        return this.request(`/api/catalogo/productos/${productId}/receta`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async deleteRecipeIngredient(productId, materiaId) {
        return this.request(`/api/catalogo/productos/${productId}/receta/${materiaId}`, {
            method: 'DELETE'
        });
    },

    async syncAllStock() {
        return this.request('/api/catalogo/recetas/sincronizar', {
            method: 'POST'
        });
    },

    // ============================================================
    // 3. PEDIDOS SERVICE
    // ============================================================
    async createOrder(items, direccion, telefono, notas = '', tipoEntrega = 'delivery', sucursal = '', propina = 0) {
        let payload;
        if (items && typeof items === 'object' && !Array.isArray(items)) {
            payload = items;
        } else {
            payload = {
                items,
                direccion,
                telefono,
                notas,
                tipo_entrega: tipoEntrega,
                sucursal: sucursal || 'Sucursal Zona 10 - Terraza Gourmet',
                propina: parseFloat(propina) || 0
            };
        }
        return this.request('/api/pedidos', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
    },

    async getOrders(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/pedidos${query ? '?' + query : ''}`);
    },

    async getOrderById(id) {
        return this.request(`/api/pedidos/${id}`);
    },

    async updateOrderStatus(id, estado) {
        return this.request(`/api/pedidos/${id}/estado`, {
            method: 'PUT',
            body: JSON.stringify({ estado })
        });
    },

    async exportOrders(formato = 'json') {
        if (formato === 'csv') {
            const token = this.getToken();
            window.location.href = `/api/pedidos/exportar?formato=csv&token=${token}`;
            return { success: true };
        }
        return this.request('/api/pedidos/exportar?formato=json');
    },

    // ============================================================
    // 4. PAGOS SERVICE (PCI-DSS Compliant)
    // ============================================================
    async initPayment(pedidoId) {
        return this.request('/api/pagos/iniciar', {
            method: 'POST',
            body: JSON.stringify({ pedido_id: pedidoId })
        });
    },

    async confirmPayment(pedidoId, paymentIntentId, simularFallo = false) {
        return this.request('/api/pagos/confirmar', {
            method: 'POST',
            body: JSON.stringify({
                pedido_id: pedidoId,
                payment_intent_id: paymentIntentId,
                simular_fallo: simularFallo
            })
        });
    },

    async getPayments(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/pagos${query ? '?' + query : ''}`);
    },

    // ============================================================
    // 5. SECURITY AUDIT SERVICE
    // ============================================================
    async getAuditLogs(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`/api/audit/logs${query ? '?' + query : ''}`);
    },

    async getAuditMetrics() {
        return this.request('/api/audit/metrics');
    },

    async getAuditReport() {
        return this.request('/api/audit/report');
    },

    async exportAuditLogs(formato = 'json') {
        if (formato === 'csv') {
            const token = this.getToken();
            window.location.href = `/api/audit/export?formato=csv&token=${token}`;
            return { success: true };
        }
        return this.request('/api/audit/export?formato=json');
    },

    // ============================================================
    // 6. UTILIDADES
    // ============================================================
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2
        }).format(parseFloat(amount) || 0);
    },

    formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        return new Date(dateStr).toLocaleDateString('es-GT', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }
};
