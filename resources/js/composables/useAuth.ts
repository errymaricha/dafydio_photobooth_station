import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { ref } from 'vue'

const isAuthenticated = ref(false)

if (typeof window !== 'undefined') {
  isAuthenticated.value = Boolean(localStorage.getItem('auth_token'))
}

export function useAuth() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  const login = async (email: string, password: string) => {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/auth/login', {
        email,
        password,
      })

      const token = response.data.token ?? response.data.access_token

      if (!token) {
        throw new Error('Token tidak ditemukan')
      }

      if (typeof window !== 'undefined') {
        localStorage.setItem('auth_token', token)
        axios.defaults.headers.common.Authorization = `Bearer ${token}`
      }

      isAuthenticated.value = true

      router.visit('/dashboard')
    } catch (err: any) {
      error.value = err?.response?.data?.message ?? 'Login gagal'
    } finally {
      loading.value = false
    }
  }

  const logout = () => {
    if (typeof window !== 'undefined') {
      localStorage.removeItem('auth_token')
      delete axios.defaults.headers.common.Authorization
    }

    isAuthenticated.value = false
    router.visit('/login')
  }

  return {
    isAuthenticated,
    loading,
    error,
    login,
    logout,
  }
}