<template>
  <KioskLayout>
    <div class="kiosk-container max-w-4xl mx-auto p-6">
      
      <!-- Welcome Screen -->
      <div v-if="currentStep === 'welcome'" class="welcome-screen text-center py-16">
        <div class="mb-8">
          <ApplicationLogo class="w-24 h-24 mx-auto mb-6" />
          <h1 class="text-4xl font-bold text-gray-900 mb-4">Welcome to PhotoBox</h1>
          <p class="text-xl text-gray-600 mb-8">Create beautiful memories with custom frames</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
          <div class="step-card bg-white rounded-lg p-6 shadow-md">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2">Take Your Photo</h3>
            <p class="text-gray-600">Use the camera to capture your perfect moment</p>
          </div>
          
          <div class="step-card bg-white rounded-lg p-6 shadow-md">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2">Choose a Frame</h3>
            <p class="text-gray-600">Select from our beautiful collection of frames</p>
          </div>
          
          <div class="step-card bg-white rounded-lg p-6 shadow-md">
            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
              </svg>
            </div>
            <h3 class="text-lg font-semibold mb-2">Pay & Download</h3>
            <p class="text-gray-600">Scan QR code to pay and get your photo</p>
          </div>
        </div>
        
        <button
          @click="startSession"
          :disabled="isStarting"
          class="start-button bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white px-12 py-4 rounded-full text-xl font-semibold transition-colors shadow-lg"
        >
          <span v-if="isStarting">Starting...</span>
          <span v-else>Start Photo Session</span>
        </button>
      </div>

      <!-- Camera View -->
      <div v-else-if="currentStep === 'camera'" class="camera-section">
        <MultiPhotoCapture
          :selected-aspect-ratio="selectedAspectRatio"
          @photos-ready="onPhotosReady"
          @camera-error="onCameraError"
        />
        
        <div v-if="cameraError" class="mt-4 text-center">
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ cameraError }}
          </div>
          <button
            @click="restartSession"
            class="mt-4 px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md"
          >
            Start Over
          </button>
        </div>
      </div>

      <!-- Photo Review -->
      <div v-else-if="currentStep === 'review'" class="review-section">
        <div class="text-center mb-6">
          <h2 class="text-2xl font-bold text-gray-900 mb-2">Review Your Photo</h2>
          <p class="text-gray-600">How does it look?</p>
        </div>
        
        <PhotoPreview
          :photo-url="capturedPhotoUrl"
          :photo-info="capturedPhotoInfo"
          :show-actions="true"
          @retake="retakePhoto"
          @approve="approvePhoto"
        />
      </div>

      <!-- Border Selection -->
      <div v-else-if="currentStep === 'borders'" class="border-section">
        <BorderPicker
          @border-selected="onBorderSelected"
          @border-cleared="onBorderCleared"
        />
      </div>

      <!-- Final Preview -->
      <div v-else-if="currentStep === 'preview'" class="final-preview-section">
        <PhotoGallery
          :photos="previewPhotos"
          title="Final Preview"
          :subtitle="`Here are your ${sessionStore.photoCount} photo${sessionStore.photoCount > 1 ? 's' : ''} with the selected frame`"
          :show-actions="true"
          :show-info="false"
        >
          <template #actions>
            <button
              @click="goBackToBorders"
              class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold"
            >
              Change Frame
            </button>
            
            <!-- Development Mode: Skip Payment -->
            <button
              v-if="isDevMode"
              @click="skipToSuccess"
              class="px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-semibold"
              title="Development Mode: Skip Payment"
            >
              🚀 Skip Payment (Dev)
            </button>
            
            <button
              @click="proceedToCheckout"
              class="px-8 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold"
            >
              Proceed to Payment
            </button>
          </template>
        </PhotoGallery>
      </div>

      <!-- Checkout -->
      <div v-else-if="currentStep === 'checkout'" class="checkout-section">
        <CheckoutView
          :payment-data="paymentData"
          @payment-completed="onPaymentCompleted"
          @payment-failed="onPaymentFailed"
        />
      </div>

      <!-- Success -->
      <div v-else-if="currentStep === 'success'" class="success-section">
        <SuccessView
          :session="sessionStore.currentSession"
          @download="downloadPhoto"
          @print="printPhoto"
          @new-session="startNewSession"
        />
      </div>

    </div>
  </KioskLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useSessionStore } from '@/stores/session'
import KioskLayout from '@/Layouts/KioskLayout.vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import CameraView from '@/Components/Camera/CameraView.vue'
import MultiPhotoCapture from '@/Components/Camera/MultiPhotoCapture.vue'
import PhotoPreview from '@/Components/Camera/PhotoPreview.vue'
import PhotoGallery from '@/Components/Gallery/PhotoGallery.vue'
import BorderPicker from '@/Components/Border/BorderPicker.vue'
import CheckoutView from '@/Components/Checkout/CheckoutView.vue'
import SuccessView from '@/Components/Success/SuccessView.vue'

const sessionStore = useSessionStore()

// Reactive state
const isStarting = ref(false)
const cameraError = ref(null)
const capturedPhotoUrl = ref(null)
const capturedPhotoInfo = ref({})
const selectedBorder = ref(null)
const paymentData = ref(null)
const selectedAspectRatio = ref('4:3')
const currentStep = ref('welcome') // welcome, camera, review, borders, preview, checkout, success
const isDevMode = ref(import.meta.env.DEV) // Development mode flag

// Computed
const showBorderPicker = computed(() => {
  return currentStep.value === 'borders'
})

const previewPhotos = computed(() => {
  // Use processed photos if available, otherwise use captured photos
  if (sessionStore.processedPhotos && sessionStore.processedPhotos.length > 0) {
    return sessionStore.processedPhotos
  }
  
  // Fallback to captured photos with proper URL mapping
  return sessionStore.capturedPhotos.map(photo => ({
    id: photo.id,
    url: photo.blob_url || `/storage/${photo.original_path}`,
    preview_url: photo.blob_url || `/storage/${photo.original_path}`,
    original_path: photo.original_path,
    processed: false,
    border_applied: selectedBorder.value?.name || null
  }))
})

// Methods
async function startSession() {
  try {
    isStarting.value = true
    cameraError.value = null
    
    await sessionStore.startSession('Kiosk #1')
    await sessionStore.updateSessionStatus('capturing')
    currentStep.value = 'camera'
    
  } catch (error) {
    console.error('Failed to start session:', error)
    cameraError.value = 'Failed to start session. Please try again.'
  } finally {
    isStarting.value = false
  }
}

function onCameraReady() {
  cameraError.value = null
}

function onCameraError(error) {
  cameraError.value = error
}

function onPhotosReady(photos) {
  console.log('Photos ready:', photos)
  currentStep.value = 'borders'
}

function retakePhoto() {
  // Clear captured photo
  if (capturedPhotoUrl.value) {
    URL.revokeObjectURL(capturedPhotoUrl.value)
    capturedPhotoUrl.value = null
  }
  capturedPhotoInfo.value = {}
  selectedBorder.value = null
  
  // Return to camera
  sessionStore.updateSessionStatus('capturing')
  currentStep.value = 'camera'
}

function approvePhoto() {
  // Move to border selection
  console.log('Photo approved, moving to border selection')
  console.log('Current step before:', currentStep.value)
  currentStep.value = 'borders'
  console.log('Current step after:', currentStep.value)
}

async function onBorderSelected(border) {
  try {
    selectedBorder.value = border
    await sessionStore.selectBorder(border.id)
    
    // Compose all photos with the selected border
    const composedResult = await sessionStore.composeAllPhotos()
    console.log('Composed all photos result:', composedResult)
    
    // Show final preview before checkout
    currentStep.value = 'preview'
    
  } catch (error) {
    console.error('Failed to select border:', error)
  }
}

async function onBorderCleared() {
  selectedBorder.value = null
  
  // Compose all photos without border
  const composedResult = await sessionStore.composeAllPhotos()
  console.log('Composed all photos result (no border):', composedResult)
  
  // Show final preview before checkout
  currentStep.value = 'preview'
}

function goBackToBorders() {
  currentStep.value = 'borders'
}

function skipToSuccess() {
  // Development mode: Skip payment and go directly to success
  console.log('🚀 Development mode: Skipping payment')
  sessionStore.updateSessionStatus('paid')
  currentStep.value = 'success'
}

async function proceedToCheckout() {
  try {
    const checkout = await sessionStore.checkout()
    paymentData.value = checkout
    currentStep.value = 'checkout'
    
  } catch (error) {
    console.error('Failed to checkout:', error)
  }
}

function onPaymentCompleted() {
  sessionStore.updateSessionStatus('paid')
  currentStep.value = 'success'
}

function onPaymentFailed() {
  // Handle payment failure
  console.error('Payment failed')
  currentStep.value = 'borders' // Go back to border selection
}

function downloadPhoto() {
  // Implement download logic
  console.log('Download photo')
}

function printPhoto() {
  // Implement print logic
  console.log('Print photo')
}

function startNewSession() {
  sessionStore.resetSession()
  if (capturedPhotoUrl.value) {
    URL.revokeObjectURL(capturedPhotoUrl.value)
    capturedPhotoUrl.value = null
  }
  capturedPhotoInfo.value = {}
  selectedBorder.value = null
  paymentData.value = null
  currentStep.value = 'welcome'
}

function restartSession() {
  startNewSession()
}

// Lifecycle
onMounted(() => {
  // Reset any existing session on page load
  sessionStore.resetSession()
  currentStep.value = 'welcome'
})
</script>

<style scoped>
.kiosk-container {
  min-height: calc(100vh - 4rem);
}

.start-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
}

.step-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}
</style>