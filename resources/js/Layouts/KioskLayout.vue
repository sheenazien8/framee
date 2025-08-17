<template>
  <div class="kiosk-layout min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <!-- Logo -->
          <div class="flex items-center">
            <ApplicationLogo class="w-8 h-8 mr-3" />
            <h1 class="text-xl font-bold text-gray-900">PhotoBox</h1>
          </div>

          <!-- Session Info -->
          <div v-if="sessionStore.hasSession" class="flex items-center space-x-4 text-sm text-gray-600">
            <div class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Session: {{ sessionStore.currentSession?.code }}
            </div>
            <div class="flex items-center">
              <div class="w-2 h-2 rounded-full mr-1"
                   :class="statusIndicatorClass"></div>
              {{ statusText }}
            </div>
            <div v-if="sessionStore.hasPhotos" class="flex items-center">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              {{ sessionStore.photoCount }} photo{{ sessionStore.photoCount > 1 ? 's' : '' }}
            </div>
          </div>

          <!-- Development Mode Indicator -->
          <div v-if="isDevelopmentMode" class="bg-orange-100 border border-orange-400 text-orange-800 px-3 py-1 rounded-full text-xs font-semibold">
            🚀 DEV MODE
          </div>

          <!-- Exit Button -->
          <button
            @click="exitKiosk"
            class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
            title="Exit Kiosk Mode"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 relative">
      <slot />
    </main>

    <!-- Idle Timeout Overlay -->
    <div v-if="showIdleWarning" 
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-8 max-w-md mx-4 text-center">
        <svg class="mx-auto h-12 w-12 text-yellow-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Session Timeout Warning</h3>
        <p class="text-gray-600 mb-4">
          Your session will end in {{ idleCountdown }} seconds due to inactivity.
        </p>
        <div class="flex space-x-3">
          <button
            @click="extendSession"
            class="flex-1 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md"
          >
            Continue Session
          </button>
          <button
            @click="endSession"
            class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded-md"
          >
            End Session
          </button>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="sessionStore.isLoading" 
         class="fixed inset-0 bg-black/30 flex items-center justify-center z-40">
      <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
        <span class="text-gray-700">Loading...</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useSessionStore } from '@/stores/session'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'

const sessionStore = useSessionStore()

// Development mode
const isDevelopmentMode = computed(() => import.meta.env.DEV)

// Idle timeout management
const showIdleWarning = ref(false)
const idleCountdown = ref(30)
const idleTimer = ref(null)
const idleWarningTimer = ref(null)
const lastActivity = ref(Date.now())

// Computed
const statusIndicatorClass = computed(() => {
  if (sessionStore.isIdle) return 'bg-gray-400'
  if (sessionStore.isCapturing) return 'bg-yellow-400'
  if (sessionStore.isReview) return 'bg-blue-400'
  if (sessionStore.isCheckout) return 'bg-orange-400'
  if (sessionStore.isPaid) return 'bg-green-400'
  return 'bg-gray-400'
})

const statusText = computed(() => {
  if (sessionStore.isIdle) return 'Ready'
  if (sessionStore.isCapturing) return 'Taking Photo'
  if (sessionStore.isReview) return 'Review Photo'
  if (sessionStore.isCheckout) return 'Processing Payment'
  if (sessionStore.isPaid) return 'Payment Complete'
  return 'Unknown'
})

// Methods
function exitKiosk() {
  if (sessionStore.hasSession) {
    if (confirm('Are you sure you want to exit? Your session will be lost.')) {
      sessionStore.resetSession()
      router.visit('/')
    }
  } else {
    router.visit('/')
  }
}

function resetIdleTimer() {
  lastActivity.value = Date.now()
  showIdleWarning.value = false
  
  // Clear existing timers
  if (idleTimer.value) clearTimeout(idleTimer.value)
  if (idleWarningTimer.value) clearInterval(idleWarningTimer.value)
  
  // Set new idle timer (5 minutes)
  idleTimer.value = setTimeout(() => {
    showIdleWarning.value = true
    idleCountdown.value = 30
    
    // Start countdown
    idleWarningTimer.value = setInterval(() => {
      idleCountdown.value--
      if (idleCountdown.value <= 0) {
        endSession()
      }
    }, 1000)
  }, 5 * 60 * 1000) // 5 minutes
}

function extendSession() {
  resetIdleTimer()
}

function endSession() {
  sessionStore.resetSession()
  showIdleWarning.value = false
  if (idleWarningTimer.value) clearInterval(idleWarningTimer.value)
  router.visit('/')
}

function handleActivity() {
  if (!showIdleWarning.value) {
    resetIdleTimer()
  }
}

// Lifecycle
onMounted(() => {
  // Setup activity listeners
  const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click']
  events.forEach(event => {
    document.addEventListener(event, handleActivity, true)
  })
  
  // Start idle timer
  resetIdleTimer()
})

onUnmounted(() => {
  // Cleanup
  const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click']
  events.forEach(event => {
    document.removeEventListener(event, handleActivity, true)
  })
  
  if (idleTimer.value) clearTimeout(idleTimer.value)
  if (idleWarningTimer.value) clearInterval(idleWarningTimer.value)
})
</script>

<style scoped>
.kiosk-layout {
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}

/* Prevent context menu */
.kiosk-layout * {
  -webkit-touch-callout: none;
  -webkit-user-select: none;
  -khtml-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}

/* Allow text selection in inputs */
.kiosk-layout input,
.kiosk-layout textarea {
  -webkit-user-select: text;
  -moz-user-select: text;
  -ms-user-select: text;
  user-select: text;
}
</style>