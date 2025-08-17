<template>
  <div class="border-picker">
    <!-- Header -->
    <div class="picker-header mb-6">
      <h2 class="text-2xl font-bold text-center mb-2">Choose a Frame</h2>
      <p class="text-gray-600 text-center">Select a beautiful frame for your photo</p>
    </div>

    <!-- Category Tabs -->
    <div class="category-tabs mb-6 overflow-x-auto">
      <div class="flex space-x-2 min-w-max px-1">
        <button
          @click="selectedCategory = null"
          :class="[
            'px-4 py-2 rounded-full whitespace-nowrap transition-colors',
            selectedCategory === null 
              ? 'bg-blue-500 text-white' 
              : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
          ]"
        >
          All Frames
        </button>
        <button
          v-for="category in categories"
          :key="category.id"
          @click="selectedCategory = category"
          :class="[
            'px-4 py-2 rounded-full whitespace-nowrap transition-colors',
            selectedCategory?.id === category.id 
              ? 'bg-blue-500 text-white' 
              : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
          ]"
        >
          {{ category.name }}
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="search-bar mb-6">
      <div class="relative">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search frames..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
        <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex justify-center items-center h-64">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center text-red-500 p-8">
      <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p>{{ error }}</p>
      <button @click="loadBorders" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
        Try Again
      </button>
    </div>

    <!-- Border Grid -->
    <div v-else class="border-grid">
      <!-- No Results -->
      <div v-if="filteredBorders.length === 0" class="text-center text-gray-500 p-8">
        <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29.82-5.657 2.172" />
        </svg>
        <p>No frames found matching your search.</p>
      </div>

      <!-- Border Items -->
      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div
          v-for="border in filteredBorders"
          :key="border.id"
          @click="selectBorder(border)"
          :class="[
            'border-item cursor-pointer rounded-lg overflow-hidden transition-all duration-200',
            'hover:shadow-lg hover:-translate-y-1',
            selectedBorder?.id === border.id 
              ? 'ring-4 ring-blue-500 shadow-lg transform -translate-y-1' 
              : 'border border-gray-200'
          ]"
        >
          <!-- Preview Image -->
          <div class="aspect-square bg-gray-100 relative overflow-hidden">
            <img
              :src="border.preview_url"
              :alt="border.name"
              class="w-full h-full object-cover"
              @load="onImageLoad(border.id)"
              @error="onImageError(border.id)"
            />
            
            <!-- Loading overlay -->
            <div v-if="loadingImages.has(border.id)" 
                 class="absolute inset-0 bg-gray-200 flex items-center justify-center">
              <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
            </div>
            
            <!-- Selected indicator -->
            <div v-if="selectedBorder?.id === border.id" 
                 class="absolute top-2 right-2 bg-blue-500 rounded-full p-1">
              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>

          <!-- Border Info -->
          <div class="p-3">
            <h3 class="font-medium text-sm text-gray-900 mb-1">{{ border.name }}</h3>
            <p class="text-xs text-gray-500">{{ border.aspect_ratio }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div v-if="selectedBorder" class="action-buttons mt-8 flex justify-center space-x-4">
      <button
        @click="clearSelection"
        class="px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md transition-colors"
      >
        No Frame
      </button>
      <button
        @click="confirmSelection"
        class="px-8 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition-colors"
      >
        Continue with {{ selectedBorder.name }}
      </button>
    </div>

    <!-- Preview Modal -->
    <div v-if="previewBorder" @click="closePreview" 
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div @click.stop class="bg-white rounded-lg max-w-lg w-full overflow-hidden">
        <div class="aspect-square bg-gray-100">
          <img
            :src="previewBorder.preview_url"
            :alt="previewBorder.name"
            class="w-full h-full object-cover"
          />
        </div>
        <div class="p-4">
          <h3 class="font-bold text-lg mb-2">{{ previewBorder.name }}</h3>
          <p class="text-gray-600 text-sm mb-2">Aspect Ratio: {{ previewBorder.aspect_ratio }}</p>
          <p class="text-gray-600 text-sm mb-4">{{ previewBorder.category?.name }} Collection</p>
          <div class="flex space-x-2">
            <button
              @click="selectBorder(previewBorder)"
              class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md"
            >
              Select This Frame
            </button>
            <button
              @click="closePreview"
              class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  preselectedBorder: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['border-selected', 'border-cleared'])

// Reactive state
const borders = ref([])
const categories = ref([])
const selectedBorder = ref(props.preselectedBorder)
const selectedCategory = ref(null)
const searchQuery = ref('')
const isLoading = ref(true)
const error = ref(null)
const loadingImages = ref(new Set())
const previewBorder = ref(null)

// Computed
const filteredBorders = computed(() => {
  let filtered = borders.value

  // Filter by category
  if (selectedCategory.value) {
    filtered = filtered.filter(border => border.category_id === selectedCategory.value.id)
  }

  // Filter by search query
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim()
    filtered = filtered.filter(border => 
      border.name.toLowerCase().includes(query) ||
      border.category?.name.toLowerCase().includes(query)
    )
  }

  // Only show active borders
  return filtered.filter(border => border.is_active)
})

// Methods
async function loadBorders() {
  try {
    isLoading.value = true
    error.value = null

    const [bordersResponse, categoriesResponse] = await Promise.all([
      axios.get('/api/v1/borders'),
      axios.get('/api/v1/border-categories')
    ])

    borders.value = bordersResponse.data.data || bordersResponse.data
    categories.value = categoriesResponse.data.data || categoriesResponse.data

  } catch (err) {
    console.error('Failed to load borders:', err)
    error.value = 'Failed to load frames. Please try again.'
  } finally {
    isLoading.value = false
  }
}

function selectBorder(border) {
  selectedBorder.value = border
  closePreview()
  emit('border-selected', border)
}

function clearSelection() {
  selectedBorder.value = null
  emit('border-cleared')
}

function confirmSelection() {
  if (selectedBorder.value) {
    emit('border-selected', selectedBorder.value)
  }
}

function showPreview(border) {
  previewBorder.value = border
}

function closePreview() {
  previewBorder.value = null
}

function onImageLoad(borderId) {
  loadingImages.value.delete(borderId)
}

function onImageError(borderId) {
  loadingImages.value.delete(borderId)
  console.warn(`Failed to load preview for border ${borderId}`)
}

// Lifecycle
onMounted(() => {
  loadBorders()
})
</script>

<style scoped>
.border-picker {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
}

.category-tabs {
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.category-tabs::-webkit-scrollbar {
  display: none;
}

.border-item {
  background: white;
}

.border-item:hover {
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

@media (max-width: 640px) {
  .border-grid .grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }
}
</style>