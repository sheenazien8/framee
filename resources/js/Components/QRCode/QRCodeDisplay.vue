<template>
  <div class="qr-code-display">
    <div class="qr-container bg-white p-6 rounded-lg shadow-lg text-center">
      <!-- QR Code Title -->
      <h3 class="text-lg font-semibold text-gray-800 mb-4">
        {{ title }}
      </h3>
      
      <!-- QR Code Canvas -->
      <div class="qr-code-wrapper flex justify-center mb-4">
        <canvas 
          ref="qrCanvas" 
          class="border border-gray-200 rounded-lg shadow-sm"
          :class="{ 'animate-pulse': isGenerating }"
        ></canvas>
      </div>
      
      <!-- Loading State -->
      <div v-if="isGenerating" class="text-gray-500 text-sm">
        Generating QR Code...
      </div>
      
      <!-- Error State -->
      <div v-if="error" class="text-red-500 text-sm mb-4">
        Failed to generate QR code: {{ error }}
      </div>
      
      <!-- Instructions -->
      <div v-if="!isGenerating && !error" class="instructions text-sm text-gray-600">
        <p class="mb-2">📱 Scan with your phone camera</p>
        <p class="text-xs">{{ description }}</p>
      </div>
      
      <!-- URL Display (for debugging/fallback) -->
      <div v-if="showUrl && url && !isGenerating" class="mt-4 pt-4 border-t border-gray-200">
        <p class="text-xs text-gray-500 mb-2">Or copy this link:</p>
        <div class="bg-gray-100 p-2 rounded text-xs break-all font-mono">
          {{ url }}
        </div>
        <button 
          @click="copyToClipboard"
          class="mt-2 px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs rounded transition-colors"
        >
          {{ copied ? '✓ Copied!' : 'Copy Link' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  url: {
    type: String,
    required: true
  },
  title: {
    type: String,
    default: 'Download Your Photos'
  },
  description: {
    type: String,
    default: 'Scan to download your photos to your device'
  },
  size: {
    type: Number,
    default: 200
  },
  showUrl: {
    type: Boolean,
    default: false
  },
  errorCorrectionLevel: {
    type: String,
    default: 'M', // L, M, Q, H
    validator: (value) => ['L', 'M', 'Q', 'H'].includes(value)
  }
})

const emit = defineEmits(['generated', 'error'])

// Refs
const qrCanvas = ref(null)
const isGenerating = ref(false)
const error = ref(null)
const copied = ref(false)

// QR Code options
const qrOptions = {
  errorCorrectionLevel: props.errorCorrectionLevel,
  type: 'image/png',
  quality: 0.92,
  margin: 2,
  color: {
    dark: '#1F2937', // Dark gray
    light: '#FFFFFF' // White
  },
  width: props.size
}

// Methods
async function generateQRCode() {
  if (!props.url || !qrCanvas.value) return
  
  try {
    isGenerating.value = true
    error.value = null
    
    await QRCode.toCanvas(qrCanvas.value, props.url, qrOptions)
    
    emit('generated', {
      url: props.url,
      canvas: qrCanvas.value
    })
    
  } catch (err) {
    console.error('QR Code generation failed:', err)
    error.value = err.message || 'Unknown error occurred'
    emit('error', err)
  } finally {
    isGenerating.value = false
  }
}

async function copyToClipboard() {
  try {
    await navigator.clipboard.writeText(props.url)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to copy to clipboard:', err)
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = props.url
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand('copy')
    document.body.removeChild(textArea)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  }
}

// Watchers
watch(() => props.url, () => {
  if (props.url) {
    nextTick(() => generateQRCode())
  }
}, { immediate: false })

// Lifecycle
onMounted(() => {
  if (props.url) {
    nextTick(() => generateQRCode())
  }
})
</script>

<style scoped>
.qr-code-display {
  max-width: 300px;
  margin: 0 auto;
}

.qr-container {
  background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
  border: 1px solid #e2e8f0;
}

.qr-code-wrapper canvas {
  max-width: 100%;
  height: auto;
}

.instructions {
  line-height: 1.4;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>