import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useSessionStore = defineStore('session', () => {
  // State
  const currentSession = ref(null)
  const selectedBorder = ref(null)
  const capturedPhotos = ref([])
  const paymentStatus = ref('idle')
  const isLoading = ref(false)

  // Computed
  const hasSession = computed(() => !!currentSession.value)
  const hasPhotos = computed(() => capturedPhotos.value.length > 0)
  const isIdle = computed(() => currentSession.value?.status === 'idle')
  const isCapturing = computed(() => currentSession.value?.status === 'capturing')
  const isReview = computed(() => currentSession.value?.status === 'review')
  const isCheckout = computed(() => currentSession.value?.status === 'checkout')
  const isPaid = computed(() => currentSession.value?.status === 'paid')
  const isCompleted = computed(() => currentSession.value?.status === 'completed')

  // Actions
  async function startSession(kioskLabel = null) {
    isLoading.value = true
    try {
      const response = await axios.post('/api/v1/sessions', {
        kiosk_label: kioskLabel
      })
      currentSession.value = response.data
      return response.data
    } catch (error) {
      console.error('Failed to start session:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function updateSessionStatus(status) {
    if (!currentSession.value) return

    isLoading.value = true
    try {
      const response = await axios.patch(`/api/v1/sessions/${currentSession.value.code}`, {
        status
      })
      currentSession.value = response.data
      return response.data
    } catch (error) {
      console.error('Failed to update session status:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function uploadPhoto(photoBlob) {
    if (!currentSession.value) throw new Error('No active session')

    isLoading.value = true
    try {
      const formData = new FormData()
      formData.append('photo', photoBlob, 'photo.jpg')
      
      const response = await axios.post(`/api/v1/sessions/${currentSession.value.code}/photos`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      
      capturedPhotos.value.push(response.data)
      return response.data
    } catch (error) {
      console.error('Failed to upload photo:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function selectBorder(borderId) {
    if (!currentSession.value) throw new Error('No active session')

    isLoading.value = true
    try {
      const response = await axios.post(`/api/v1/sessions/${currentSession.value.code}/border`, {
        border_id: borderId
      })
      selectedBorder.value = response.data.border
      return response.data
    } catch (error) {
      console.error('Failed to select border:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function composeImage() {
    if (!currentSession.value) throw new Error('No active session')

    isLoading.value = true
    try {
      const response = await axios.post(`/api/v1/sessions/${currentSession.value.code}/compose`)
      return response.data
    } catch (error) {
      console.error('Failed to compose image:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function checkout() {
    if (!currentSession.value) throw new Error('No active session')

    isLoading.value = true
    try {
      const response = await axios.post(`/api/v1/sessions/${currentSession.value.code}/checkout`)
      paymentStatus.value = 'pending'
      return response.data
    } catch (error) {
      console.error('Failed to checkout:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function checkPaymentStatus() {
    if (!currentSession.value) return

    try {
      const response = await axios.get(`/api/v1/sessions/${currentSession.value.code}/status`)
      currentSession.value = response.data.session
      paymentStatus.value = response.data.payment_status
      return response.data
    } catch (error) {
      console.error('Failed to check payment status:', error)
      throw error
    }
  }

  function resetSession() {
    currentSession.value = null
    selectedBorder.value = null
    capturedPhotos.value = []
    paymentStatus.value = 'idle'
    isLoading.value = false
  }

  return {
    // State
    currentSession,
    selectedBorder,
    capturedPhotos,
    paymentStatus,
    isLoading,
    
    // Computed
    hasSession,
    hasPhotos,
    isIdle,
    isCapturing,
    isReview,
    isCheckout,
    isPaid,
    isCompleted,
    
    // Actions
    startSession,
    updateSessionStatus,
    uploadPhoto,
    selectBorder,
    composeImage,
    checkout,
    checkPaymentStatus,
    resetSession
  }
})