import axios from 'axios';
type ApiTarget = string | { url: string };

const resolveUrl = (target: ApiTarget): string => {
    return typeof target === 'string' ? target : target.url;
};

export function useApi() {
    const get = async <T = unknown>(
        target: ApiTarget,
        params: Record<string, unknown> = {},
    ): Promise<T> => {
        const { data } = await axios.get<T>(resolveUrl(target), { params });

        return data;
    };

    const post = async <T = unknown>(
        target: ApiTarget,
        payload: Record<string, unknown> | FormData = {},
    ): Promise<T> => {
        const { data } = await axios.post<T>(resolveUrl(target), payload);

        return data;
    };

    const patch = async <T = unknown>(
        target: ApiTarget,
        payload: Record<string, unknown> | FormData = {},
    ): Promise<T> => {
        const { data } = await axios.patch<T>(resolveUrl(target), payload);

        return data;
    };

    const del = async <T = unknown>(
        target: ApiTarget,
        payload: Record<string, unknown> | FormData = {},
    ): Promise<T> => {
        const { data } = await axios.delete<T>(resolveUrl(target), {
            data: payload,
        });

        return data;
    };

    return { get, post, patch, del };
}
