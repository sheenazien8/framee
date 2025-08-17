<template>
  <div class="photo-gallery">
    <div class="gallery-header mb-6 text-center">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ title }}</h2>
      <p v-if="subtitle" class="text-gray-600">{{ subtitle }}</p>
    </div>

    <div v-if="photos.length > 0" class="gallery-grid">
      <!-- Main Photo Display (if single photo or selected) -->
      <div v-if="photos.length === 1 || selectedPhoto" class="main-photo mb-6">
        <img
          :src="getPhotoUrl(selectedPhoto || photos[0])"
          :alt="`Photo ${selectedPhotoIndex + 1}`"
          class="w-full max-w-2xl mx-auto rounded-lg shadow-lg"
        />
        
        <div v-if="photos.length > 1" class="photo-navigation mt-4 flex justify-center space-x-4">
          <button
            @click="previousPhoto"
            :disabled="selectedPhotoIndex === 0"
            class="px-4 py-2 bg-gray-500 hover:bg-gray-600 disabled:bg-gray-300 text-white rounded-md transition-colors"
          >
            Previous
          </button>
          <span class="px-4 py-2 bg-gray-100 rounded-md">
            {{ selectedPhotoIndex + 1 }} of {{ photos.length }}
          </span>
          <button
            @click="nextPhoto"
            :disabled="selectedPhotoIndex === photos.length - 1"
            class="px-4 py-2 bg-gray-500 hover:bg-gray-600 disabled:bg-gray-300 text-white rounded-md transition-colors"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Thumbnail Grid (if multiple photos) -->
      <div v-if="photos.length > 1" class="thumbnails-grid">
        <h3 class="text-lg font-semibold mb-4">All Photos</h3>
        <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          <div
            v-for="(photo, index) in photos"
            :key="photo.id"
            @click="selectPhoto(index)"
            :class="[
              'thumbnail cursor-pointer rounded-lg overflow-hidden border-2 transition-all',
              selectedPhotoIndex === index 
                ? 'border-blue-500 ring-2 ring-blue-200' 
                : 'border-gray-200 hover:border-blue-300'
            ]"
          >
            <img
              :src="getPhotoUrl(photo)"
              :alt="`Photo ${index + 1}`"
              class="w-full aspect-square object-cover"
            />
            <div class="absolute top-1 left-1 bg-blue-500 text-white text-xs px-2 py-1 rounded">
              {{ index + 1 }}
            </div>
          </div>
        </div>
      </div>

      <!-- Photo Information -->
      <div v-if="showInfo && selectedPhoto" class="photo-info mt-6 bg-gray-50 rounded-lg p-4">
        <h4 class="font-semibold mb-2">Photo Details</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <span class="text-gray-600">Size:</span>
            <span class="ml-1">{{ selectedPhoto.width }}×{{ selectedPhoto.height }}</span>
          </div>
          <div v-if="selectedPhoto.border_applied">
            <span class="text-gray-600">Border:</span>
            <span class="ml-1">{{ selectedPhoto.border_applied }}</span>
          </div>
          <div>
            <span class="text-gray-600">Status:</span>
            <span class="ml-1">{{ selectedPhoto.processed ? 'Processed' : 'Original' }}</span>
          </div>
          <div v-if="selectedPhoto.file_size">
            <span class="text-gray-600">File Size:</span>
            <span class="ml-1">{{ formatFileSize(selectedPhoto.file_size) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state text-center py-12">
      <svg class="mx-auto h-16 w-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No Photos</h3>
      <p class="text-gray-600">No photos available to display.</p>
    </div>

    <!-- Action Buttons -->
    <div v-if="photos.length > 0 && showActions" class="action-buttons mt-8 flex justify-center space-x-4">
      <slot name="actions" :selectedPhoto="selectedPhoto" :allPhotos="photos">
        <button
          @click="$emit('back')"
          class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold transition-colors"
        >
          Back
        </button>
        <button
          @click="$emit('continue', { selectedPhoto, allPhotos: photos })"
          class="px-8 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold transition-colors"
        >
          Continue
        </button>
      </slot>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  photos: {
    type: Array,
    default: () => []
  },
  title: {
    type: String,
    default: 'Photo Gallery'
  },
  subtitle: {
    type: String,
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
  initialPhotoIndex: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['back', 'continue', 'photo-selected'])

// Reactive state
const selectedPhotoIndex = ref(props.initialPhotoIndex)

// Computed
const selectedPhoto = computed(() => {
  return props.photos[selectedPhotoIndex.value] || null
})

// Methods
function selectPhoto(index) {
  selectedPhotoIndex.value = index
  emit('photo-selected', selectedPhoto.value, index)
}

function previousPhoto() {
  if (selectedPhotoIndex.value > 0) {
    selectPhoto(selectedPhotoIndex.value - 1)
  }
}

function nextPhoto() {
  if (selectedPhotoIndex.value < props.photos.length - 1) {
    selectPhoto(selectedPhotoIndex.value + 1)
  }
}

function formatFileSize(bytes) {
  if (!bytes) return 'Unknown'
  
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  
  return `${size.toFixed(1)} ${units[unitIndex]}`
}

function getPhotoUrl(photo) {
  // Priority: preview_url > processed_path > url > original_path with fallback
  if (photo.preview_url) return photo.preview_url
  if (photo.processed_path) return `/storage/${photo.processed_path}`
  if (photo.url) return photo.url
  if (photo.original_path) return `/storage/${photo.original_path}`
  if (photo.blob_url) return photo.blob_url
  
  // Last resort fallback
  console.warn('No valid photo URL found for photo:', photo)
  return '/images/placeholder.jpg'
}

// Watch for prop changes
watch(() => props.photos, () => {
  if (selectedPhotoIndex.value >= props.photos.length) {
    selectedPhotoIndex.value = Math.max(0, props.photos.length - 1)
  }
})

// Keyboard navigation
import { onMounted, onUnmounted } from 'vue'

function handleKeydown(event) {
  if (props.photos.length > 1) {
    if (event.key === 'ArrowLeft') {
      previousPhoto()
    } else if (event.key === 'ArrowRight') {
      nextPhoto()
    }
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.thumbnail {
  position: relative;
  aspect-ratio: 1;
}

.thumbnail:hover {
  transform: scale(1.05);
}

.main-photo img {
  max-height: 70vh;
  object-fit: contain;
}

.gallery-grid {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>