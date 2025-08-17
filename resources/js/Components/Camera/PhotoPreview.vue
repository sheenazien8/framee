<template>
  <div class="photo-preview">
    <!-- Main Photo Display -->
    <div class="photo-container relative bg-gray-100 rounded-lg overflow-hidden">
      <img
        v-if="photoUrl"
        :src="photoUrl"
        :alt="altText"
        class="w-full h-full object-cover"
        @load="onImageLoad"
        @error="onImageError"
      />
      
      <!-- Loading State -->
      <div v-else-if="isLoading" class="flex items-center justify-center h-64">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
      </div>
      
      <!-- Error State -->
      <div v-else-if="error" class="flex items-center justify-center h-64 text-red-500">
        <div class="text-center">
          <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-sm">{{ error }}</p>
        </div>
      </div>
      
      <!-- Border Overlay Preview -->
      <div v-if="borderOverlay && photoUrl" 
           class="absolute inset-0 pointer-events-none"
           :style="borderOverlayStyle">
        <img
          :src="borderOverlay.preview_url"
          :alt="borderOverlay.name"
          class="w-full h-full object-cover"
          style="mix-blend-mode: multiply;"
        />
      </div>
      
      <!-- Photo Info Overlay -->
      <div v-if="showInfo && photoInfo" 
           class="absolute bottom-0 left-0 right-0 bg-black/50 text-white p-2 text-xs">
        <div class="flex justify-between items-center">
          <span>{{ photoInfo.width }}×{{ photoInfo.height }}</span>
          <span v-if="photoInfo.aspectRatio">{{ photoInfo.aspectRatio }}</span>
          <span>{{ formatFileSize(photoInfo.size) }}</span>
        </div>
      </div>
    </div>

    <!-- Photo Actions -->
    <div v-if="showActions" class="photo-actions mt-4 flex justify-center space-x-3">
      <button
        @click="$emit('retake')"
        class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md transition-colors"
      >
        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Retake
      </button>
      
      <button
        @click="$emit('approve')"
        class="px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-md transition-colors"
      >
        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M5 13l4 4L19 7" />
        </svg>
        Use This Photo
      </button>
    </div>

    <!-- Crop/Zoom Controls -->
    <div v-if="showCropControls" class="crop-controls mt-4">
      <div class="flex items-center justify-center space-x-4">
        <label class="text-sm font-medium">Zoom:</label>
        <input
          v-model.number="cropZoom"
          type="range"
          min="1"
          max="3"
          step="0.1"
          class="flex-1 max-w-xs"
          @input="updateCrop"
        />
        <span class="text-sm text-gray-600">{{ Math.round(cropZoom * 100) }}%</span>
      </div>
      
      <div class="flex items-center justify-center space-x-4 mt-2">
        <button
          @click="resetCrop"
          class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-sm rounded"
        >
          Reset
        </button>
        <button
          @click="applyCrop"
          class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded"
        >
          Apply Crop
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  photoUrl: {
    type: String,
    default: null
  },
  photoBlob: {
    type: Blob,
    default: null
  },
  photoInfo: {
    type: Object,
    default: () => ({})
  },
  borderOverlay: {
    type: Object,
    default: null
  },
  showActions: {
    type: Boolean,
    default: true
  },
  showInfo: {
    type: Boolean,
    default: false
  },
  showCropControls: {
    type: Boolean,
    default: false
  },
  altText: {
    type: String,
    default: 'Captured photo'
  }
})

const emit = defineEmits(['retake', 'approve', 'crop-updated', 'image-load', 'image-error'])

// Reactive state
const isLoading = ref(false)
const error = ref(null)
const cropZoom = ref(1)
const cropX = ref(0)
const cropY = ref(0)

// Computed
const borderOverlayStyle = computed(() => {
  if (!props.borderOverlay) return {}
  
  return {
    opacity: 0.8,
    pointerEvents: 'none'
  }
})

// Methods
function onImageLoad(event) {
  isLoading.value = false
  error.value = null
  emit('image-load', event)
}

function onImageError(event) {
  isLoading.value = false
  error.value = 'Failed to load image'
  emit('image-error', event)
}

function formatFileSize(bytes) {
  if (!bytes) return 'Unknown size'
  
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  
  return `${size.toFixed(1)} ${units[unitIndex]}`
}

function updateCrop() {
  emit('crop-updated', {
    zoom: cropZoom.value,
    x: cropX.value,
    y: cropY.value
  })
}

function resetCrop() {
  cropZoom.value = 1
  cropX.value = 0
  cropY.value = 0
  updateCrop()
}

function applyCrop() {
  emit('crop-applied', {
    zoom: cropZoom.value,
    x: cropX.value,
    y: cropY.value
  })
}

// Watch for photo changes
watch(() => props.photoUrl, (newUrl) => {
  if (newUrl) {
    isLoading.value = true
    error.value = null
  }
})

watch(() => props.photoBlob, (newBlob) => {
  if (newBlob) {
    isLoading.value = true
    error.value = null
  }
})
</script>

<style scoped>
.photo-preview {
  max-width: 600px;
  margin: 0 auto;
}

.photo-container {
  aspect-ratio: 4 / 3;
  min-height: 300px;
}

input[type="range"] {
  -webkit-appearance: none;
  appearance: none;
  height: 4px;
  background: #ddd;
  border-radius: 2px;
  outline: none;
}

input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  background: #3b82f6;
  border-radius: 50%;
  cursor: pointer;
}

input[type="range"]::-moz-range-thumb {
  width: 20px;
  height: 20px;
  background: #3b82f6;
  border-radius: 50%;
  cursor: pointer;
  border: none;
}
</style>