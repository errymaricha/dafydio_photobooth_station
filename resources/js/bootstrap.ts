import axios from 'axios';
import type { AxiosError, AxiosResponse } from 'axios';

axios.defaults.baseURL = '/';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withXSRFToken = true;

axios.interceptors.response.use(
    (response: AxiosResponse) => {
        return response;
    },

    (error: AxiosError) => {
        if (error.response?.status === 401) {
            if (typeof window !== 'undefined') {
                if (window.location.pathname !== '/login') {
                    window.location.href = '/login';
                }
            }
        }

        return Promise.reject(error);
    },
);

export default axios;
