<template>
  <div class="multi-photo-capture">
    <!-- Photo Count Header -->
    <div class="photo-count-header mb-6 text-center">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Take Your Photos</h2>
      <div class="flex items-center justify-center space-x-4">
        <div class="photo-counter bg-blue-100 px-4 py-2 rounded-full">
          <span class="text-blue-800 font-semibold">
            {{ sessionStore.photoCount }} / {{ sessionStore.maxPhotos }} Photos
          </span>
        </div>
        <div v-if="!sessionStore.canAddMorePhotos" class="text-orange-600 text-sm">
          Maximum photos reached
        </div>
      </div>
    </div>

    <!-- Continuous Capture Mode Toggle -->
    <div v-if="!isContinuousMode && sessionStore.photoCount === 0" class="mode-selection mb-6 text-center">
      <div class="bg-gray-100 p-4 rounded-lg max-w-md mx-auto">
        <h3 class="text-lg font-semibold mb-3">Capture Mode</h3>
        <div class="space-y-3">
          <button
            @click="startContinuousMode"
            class="w-full px-4 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition-colors"
          >
            🚀 Continuous Mode (Rapid Photos)
          </button>
          <button
            @click="showCamera = true"
            class="w-full px-4 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition-colors"
          >
            📷 Manual Mode (One by One)
          </button>
        </div>
      </div>
    </div>

    <!-- Camera Section -->
    <div v-if="showCamera" class="camera-section mb-6">
      <CameraView
        :aspect-ratio="selectedAspectRatio"
        :continuous-mode="isContinuousMode"
        :max-photos="sessionStore.maxPhotos"
        :current-photo-count="sessionStore.photoCount"
        @photo-captured="onPhotoCaptured"
        @camera-ready="onCameraReady"
        @camera-error="onCameraError"
        @continuous-complete="onContinuousComplete"
      />
    </div>

    <!-- Photo Gallery -->
    <div v-if="sessionStore.hasPhotos" class="photo-gallery mb-6">
      <h3 class="text-lg font-semibold mb-4">Captured Photos</h3>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div
          v-for="(photo, index) in sessionStore.capturedPhotos"
          :key="photo.id"
          class="photo-item relative group"
        >
          <img
            :src="getPhotoUrl(photo)"
            :alt="`Photo ${index + 1}`"
            class="w-full aspect-square object-cover rounded-lg border-2 border-gray-200 hover:border-blue-400 transition-colors"
          />
          
          <!-- Photo Actions Overlay -->
          <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center space-x-2">
            <button
              @click="retakePhoto(photo, index)"
              class="p-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-full transition-colors"
              title="Retake photo"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
            <button
              @click="removePhoto(photo)"
              class="p-2 bg-red-500 hover:bg-red-600 text-white rounded-full transition-colors"
              title="Delete photo"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
          
          <!-- Photo Number -->
          <div class="absolute top-2 left-2 bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
            {{ index + 1 }}
          </div>
        </div>
        
        <!-- Add More Photos Card -->
        <div
          v-if="sessionStore.canAddMorePhotos"
          @click="showCamera = true"
          class="add-photo-card aspect-square border-2 border-dashed border-gray-300 hover:border-blue-400 rounded-lg flex flex-col items-center justify-center cursor-pointer transition-colors bg-gray-50 hover:bg-blue-50"
        >
          <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          <span class="text-sm text-gray-600">Add Photo</span>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons flex justify-center space-x-4">
      <button
        v-if="!showCamera && sessionStore.canAddMorePhotos && !isContinuousMode"
        @click="showCamera = true"
        class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition-colors"
      >
        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        Take Another Photo
      </button>
      
      <button
        v-if="showCamera && !isContinuousMode"
        @click="showCamera = false"
        class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition-colors"
      >
        Done Taking Photos
      </button>

      <button
        v-if="isContinuousMode && sessionStore.canAddMorePhotos"
        @click="startContinuousMode"
        class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition-colors"
      >
        🚀 Start Continuous Capture
      </button>
      
      <button
        v-if="sessionStore.hasPhotos && !showCamera"
        @click="proceedToNext"
        class="px-8 py-3 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold transition-colors"
      >
        Continue with {{ sessionStore.photoCount }} Photo{{ sessionStore.photoCount > 1 ? 's' : '' }}
      </button>
    </div>

    <!-- Error Messages -->
    <div v-if="error" class="mt-4 text-center">
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded max-w-md mx-auto">
        {{ error }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useSessionStore } from '@/stores/session'
import CameraView from './CameraView.vue'

const props = defineProps({
  selectedAspectRatio: {
    type: String,
    default: '4:3'
  }
})

const emit = defineEmits(['photos-ready', 'camera-error'])

const sessionStore = useSessionStore()

// Reactive state
const showCamera = ref(false)
const error = ref(null)
const retakingIndex = ref(null)
const isContinuousMode = ref(false)

// Methods
function getPhotoUrl(photo) {
  // Use blob URL if available, otherwise use server URL
  return photo.blob_url || photo.url || `/storage/${photo.original_path}`
}

async function onPhotoCaptured(photoData) {
  try {
    error.value = null
    
    // If retaking a photo, remove the old one first
    if (retakingIndex.value !== null) {
      const oldPhoto = sessionStore.capturedPhotos[retakingIndex.value]
      await sessionStore.removePhoto(oldPhoto.id)
      retakingIndex.value = null
    }
    
    // Create object URL for immediate preview
    const blobUrl = URL.createObjectURL(photoData.blob)
    
    // Upload photo to server
    const uploadedPhoto = await sessionStore.uploadPhoto(photoData.blob)
    
    // Add blob URL for immediate display
    uploadedPhoto.blob_url = blobUrl
    
    // In manual mode, hide camera after capture
    // In continuous mode, the camera will handle the flow
    if (!isContinuousMode.value && sessionStore.canAddMorePhotos) {
      showCamera.value = false
    }
    
  } catch (error) {
    console.error('Failed to process photo:', error)
    handleError('Failed to save photo. Please try again.')
  }
}

function startContinuousMode() {
  isContinuousMode.value = true
  showCamera.value = true
}

function onContinuousComplete() {
  isContinuousMode.value = false
  showCamera.value = false
}

function onCameraReady() {
  error.value = null
}

function onCameraError(errorMessage) {
  error.value = errorMessage
  emit('camera-error', errorMessage)
}

function retakePhoto(photo, index) {
  retakingIndex.value = index
  showCamera.value = true
}

async function removePhoto(photo) {
  try {
    error.value = null
    
    // Revoke blob URL if exists
    if (photo.blob_url) {
      URL.revokeObjectURL(photo.blob_url)
    }
    
    await sessionStore.removePhoto(photo.id)
    
  } catch (error) {
    console.error('Failed to remove photo:', error)
    handleError('Failed to remove photo. Please try again.')
  }
}

function proceedToNext() {
  if (sessionStore.hasPhotos) {
    emit('photos-ready', sessionStore.capturedPhotos)
  }
}

function handleError(message) {
  error.value = message
  setTimeout(() => {
    error.value = null
  }, 5000)
}

// Cleanup blob URLs when component is unmounted
import { onUnmounted } from 'vue'

onUnmounted(() => {
  sessionStore.capturedPhotos.forEach(photo => {
    if (photo.blob_url) {
      URL.revokeObjectURL(photo.blob_url)
    }
  })
})
</script>

<style scoped>
.photo-item {
  transition: transform 0.2s ease;
}

.photo-item:hover {
  transform: scale(1.02);
}

.add-photo-card:hover {
  transform: scale(1.02);
}

.photo-counter {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.8;
  }
}
</style>