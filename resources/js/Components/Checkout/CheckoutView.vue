<template>
  <div class="checkout-view text-center">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Payment Required</h2>
      <p class="text-gray-600">Scan the QR code below to complete your payment</p>
    </div>

    <div v-if="paymentData" class="payment-section">
      <!-- QR Code Display -->
      <div class="qr-container bg-white rounded-lg p-8 mb-6 max-w-md mx-auto shadow-lg">
        <div v-if="paymentData.qr_image_url" class="qr-image mb-4">
          <img 
            :src="paymentData.qr_image_url" 
            alt="Payment QR Code"
            class="w-64 h-64 mx-auto"
          />
        </div>
        <div v-else-if="paymentData.qr_string" class="qr-fallback">
          <div class="text-xs text-gray-500 mb-2">QR Code Data:</div>
          <div class="text-xs font-mono bg-gray-100 p-2 rounded break-all">
            {{ paymentData.qr_string }}
          </div>
        </div>
        
        <div class="payment-info">
          <div class="text-lg font-semibold text-gray-900 mb-2">
            {{ formatAmount(paymentData.amount) }}
          </div>
          <div class="text-sm text-gray-600">
            Expires in {{ formatTimeRemaining() }}
          </div>
        </div>
      </div>

      <!-- Status Messages -->
      <div class="status-messages">
        <div v-if="isPolling" class="flex items-center justify-center text-blue-600 mb-4">
          <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
          Waiting for payment...
        </div>
        
        <div v-if="paymentExpired" class="text-red-600 mb-4">
          <p>Payment expired. Please generate a new QR code.</p>
          <button 
            @click="regeneratePayment"
            class="mt-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md"
          >
            Generate New QR Code
          </button>
        </div>
      </div>

      <!-- Instructions -->
      <div class="instructions text-sm text-gray-600 max-w-md mx-auto">
        <p class="mb-2">1. Open your mobile banking or e-wallet app</p>
        <p class="mb-2">2. Scan the QR code above</p>
        <p class="mb-2">3. Confirm the payment amount</p>
        <p>4. Complete the transaction</p>
      </div>
    </div>

    <!-- Mock Payment for Testing -->
    <div v-if="showMockPayment" class="mock-payment mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
      <p class="text-sm text-yellow-800 mb-3">
        <strong>Testing Mode:</strong> Click the button below to simulate a successful payment
      </p>
      <button
        @click="simulatePayment"
        class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-md text-sm"
      >
        Simulate Successful Payment
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  paymentData: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['payment-completed', 'payment-failed', 'payment-expired'])

// Reactive state
const isPolling = ref(false)
const paymentExpired = ref(false)
const pollInterval = ref(null)
const timeRemaining = ref(0)
const timeInterval = ref(null)

// Computed
const showMockPayment = computed(() => {
  return props.paymentData?.provider === 'mock' || import.meta.env.DEV
})

// Methods
function formatAmount(amount) {
  if (!amount) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(amount)
}

function formatTimeRemaining() {
  if (timeRemaining.value <= 0) return '0m 0s'
  
  const minutes = Math.floor(timeRemaining.value / 60)
  const seconds = timeRemaining.value % 60
  return `${minutes}m ${seconds}s`
}

function startPolling() {
  if (pollInterval.value) return
  
  isPolling.value = true
  pollInterval.value = setInterval(async () => {
    try {
      // In a real implementation, you'd call an API to check payment status
      // For now, we'll just simulate checking
      console.log('Checking payment status...')
      
    } catch (error) {
      console.error('Error checking payment status:', error)
    }
  }, 3000) // Check every 3 seconds
}

function stopPolling() {
  if (pollInterval.value) {
    clearInterval(pollInterval.value)
    pollInterval.value = null
  }
  isPolling.value = false
}

function startCountdown() {
  if (!props.paymentData?.expires_at) return
  
  const updateTimer = () => {
    const now = new Date()
    const expires = new Date(props.paymentData.expires_at)
    const diff = Math.max(0, Math.floor((expires - now) / 1000))
    
    timeRemaining.value = diff
    
    if (diff <= 0) {
      paymentExpired.value = true
      stopPolling()
      emit('payment-expired')
    }
  }
  
  updateTimer()
  timeInterval.value = setInterval(updateTimer, 1000)
}

function stopCountdown() {
  if (timeInterval.value) {
    clearInterval(timeInterval.value)
    timeInterval.value = null
  }
}

async function regeneratePayment() {
  try {
    // This would call the API to regenerate payment
    console.log('Regenerating payment...')
    paymentExpired.value = false
    // In real implementation, you'd emit an event to parent to regenerate
  } catch (error) {
    console.error('Failed to regenerate payment:', error)
  }
}

function simulatePayment() {
  // For testing purposes
  stopPolling()
  stopCountdown()
  emit('payment-completed')
}

// Lifecycle
onMounted(() => {
  startPolling()
  startCountdown()
})

onUnmounted(() => {
  stopPolling()
  stopCountdown()
})
</script>

<style scoped>
.qr-container {
  border: 2px solid #e5e7eb;
}

.qr-image img {
  image-rendering: pixelated;
  image-rendering: -moz-crisp-edges;
  image-rendering: crisp-edges;
}
</style>