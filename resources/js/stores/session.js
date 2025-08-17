import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useSessionStore = defineStore('session', () => {
  // State
  const currentSession = ref(null)
  const selectedBorder = ref(null)
  const capturedPhotos = ref([])
  const processedPhotos = ref([])
  const paymentStatus = ref('idle')
  const isLoading = ref(false)
  const maxPhotos = ref(6) // Maximum photos per session

  // Computed
  const hasSession = computed(() => !!currentSession.value)
  const hasPhotos = computed(() => capturedPhotos.value.length > 0)
  const canAddMorePhotos = computed(() => capturedPhotos.value.length < maxPhotos.value)
  const photoCount = computed(() => capturedPhotos.value.length)
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
    if (!canAddMorePhotos.value) throw new Error('Maximum photos reached')

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

  async function removePhoto(photoId) {
    if (!currentSession.value) throw new Error('No active session')

    isLoading.value = true
    try {
      await axios.delete(`/api/v1/sessions/${currentSession.value.code}/photos/${photoId}`)
      
      // Remove from local state
      capturedPhotos.value = capturedPhotos.value.filter(photo => photo.id !== photoId)
      processedPhotos.value = processedPhotos.value.filter(photo => photo.id !== photoId)
      
    } catch (error) {
      console.error('Failed to remove photo:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function composeAllPhotos() {
    if (!currentSession.value) throw new Error('No active session')

    isLoading.value = true
    try {
      const payload = {}
      if (selectedBorder.value) {
        payload.border_id = selectedBorder.value.id
      }
      
      console.log('Composing all photos with payload:', payload)
      
      const response = await axios.post(`/api/v1/sessions/${currentSession.value.code}/compose-all`, payload)
      
      console.log('Compose all photos response:', response.data)
      
      // Update the processedPhotos array with the response data
      if (response.data.processed_photos) {
        processedPhotos.value = response.data.processed_photos.map(photo => ({
          id: photo.id,
          url: photo.original_url,
          preview_url: photo.preview_url,
          processed: photo.processed,
          // Add additional properties for compatibility
          original_path: photo.original_url?.replace(window.location.origin + '/storage/', ''),
          processed_path: photo.preview_url?.replace(window.location.origin + '/storage/', ''),
          border_applied: response.data.border_applied,
        }))
      } else {
        processedPhotos.value = []
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to compose all photos:', error)
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
      const payload = {}
      if (selectedBorder.value) {
        payload.border_id = selectedBorder.value.id
      }
      
      const response = await axios.post(`/api/v1/sessions/${currentSession.value.code}/compose`, payload)
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

  async function refreshSession() {
    if (!currentSession.value) return

    try {
      const response = await axios.get(`/api/v1/sessions/${currentSession.value.code}`)
      currentSession.value = response.data
      
      // Update captured photos from session data
      if (response.data.photos) {
        capturedPhotos.value = response.data.photos
      }
      
      return response.data
    } catch (error) {
      console.error('Failed to refresh session:', error)
      throw error
    }
  }

  function resetSession() {
    currentSession.value = null
    selectedBorder.value = null
    capturedPhotos.value = []
    processedPhotos.value = []
    paymentStatus.value = 'idle'
    isLoading.value = false
  }

  return {
    // State
    currentSession,
    selectedBorder,
    capturedPhotos,
    processedPhotos,
    paymentStatus,
    isLoading,
    maxPhotos,
    
    // Computed
    hasSession,
    hasPhotos,
    canAddMorePhotos,
    photoCount,
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
    removePhoto,
    selectBorder,
    composeImage,
    composeAllPhotos,
    checkout,
    checkPaymentStatus,
    refreshSession,
    resetSession
  }
})