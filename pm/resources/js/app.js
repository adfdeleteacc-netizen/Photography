import Alpine from 'alpinejs';
import axios from 'axios';

window.axios = axios;
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ₹ formatting helper used by the dashboards (API money fields are paise or rupees as documented per endpoint)
window.inr = (n) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(n);

window.Alpine = Alpine;
Alpine.start();
