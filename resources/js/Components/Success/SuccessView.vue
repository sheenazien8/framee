<template>
  <div class="success-view text-center py-16">
    <!-- Success Animation -->
    <div class="success-animation mb-8">
      <div class="w-24 h-24 mx-auto mb-6 bg-green-100 rounded-full flex items-center justify-center">
        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <h1 class="text-3xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
      <p class="text-xl text-gray-600 mb-8">Your {{ photoCount }} photo{{ photoCount > 1 ? 's are' : ' is' }} ready for download</p>
    </div>

    <!-- Session Info -->
    <div v-if="session" class="session-info bg-white rounded-lg p-6 mb-8 max-w-md mx-auto shadow-md">
      <div class="text-sm text-gray-600 mb-2">Session Code</div>
      <div class="text-lg font-mono font-semibold text-gray-900 mb-4">{{ session.code }}</div>

      <div class="text-sm text-gray-600 mb-2">Amount Paid</div>
      <div class="text-lg font-semibold text-green-600">{{ formatAmount(session.total_price) }}</div>
    </div>

    <!-- Download QR Code Section -->
    <div class="download-section mb-8">
      <div class="qr-container bg-white p-6 rounded-lg shadow-lg text-center max-w-sm mx-auto">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
          📱 Download Your Photos
        </h3>

        <!-- QR Code Image -->
        <div class="qr-code-wrapper flex justify-center mb-4">
          <img
            :src="`/api/v1/sessions/${session.code}/download/qr`"
            alt="Download QR Code"
            class="w-48 h-48 border border-gray-200 rounded-lg shadow-sm"
            @error="onQRError"
          />
        </div>

        <!-- Instructions -->
        <div class="instructions text-sm text-gray-600">
          <p class="mb-2">📱 Scan with your phone camera</p>
          <p class="text-xs">Point your camera at the QR code to download photos to your device</p>
        </div>
      </div>
    </div>

    <!-- Print Button -->
    <div class="print-section flex justify-center mb-8">
      <button
        @click="handlePrint"
        :disabled="isPrinting"
        class="print-btn bg-green-500 hover:bg-green-600 disabled:bg-gray-400 text-white px-8 py-3 rounded-lg text-lg font-semibold transition-colors flex items-center justify-center"
      >
        <svg v-if="!isPrinting" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        <div v-else class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
        {{ isPrinting ? 'Printing...' : 'Print Photo' }}
      </button>
    </div>

    <!-- Download Instructions -->
    <div class="instructions text-sm text-gray-600 max-w-md mx-auto mb-8">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-semibold text-blue-900 mb-2">How to Download:</h3>
        <p class="text-blue-800">
          📱 Simply scan the QR code above with your phone camera to download your photos instantly!
        </p>
      </div>
    </div>

    <!-- Start New Session -->
    <div class="new-session">
      <button
        @click="handleNewSession"
        class="new-session-btn bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md transition-colors"
      >
        Take Another Photo
      </button>
    </div>

    <!-- Error Messages -->
    <div v-if="errorMessage" class="error-message mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md max-w-md mx-auto">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  session: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['download', 'print', 'new-session'])

// Reactive state
const isDownloading = ref(false)
const isPrinting = ref(false)
const errorMessage = ref(null)

// Computed
const photoCount = computed(() => {
  return props.session?.photos?.length || 1
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

async function handleDownload() {
  try {
    isDownloading.value = true
    errorMessage.value = null

    // Use direct download endpoint
    const downloadUrl = `/api/v1/sessions/${props.session.code}/download/direct`

    // Create a temporary link and trigger download
    const link = document.createElement('a')
    link.href = downloadUrl
    link.download = `${props.session.code}_photo.jpg`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    emit('download', { filename: `${props.session.code}_photo.jpg` })

  } catch (error) {
    console.error('Download failed:', error)
    errorMessage.value = error.response?.data?.error || 'Download failed. Please try again.'
  } finally {
    isDownloading.value = false
  }
}

async function handlePrint() {
  try {
    isPrinting.value = true
    errorMessage.value = null

    // Get download URL and open in print dialog
    const response = await axios.get(`/api/v1/sessions/${props.session.code}/print`)
    console.log(response)

    if (response.data.download_url) {
      // Open print window
      const printWindow = window.open('', '_blank')
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Print Photo - ${props.session.code}</title>
          <style>
            @media print {
              @page { margin: 0; }
              body { margin: 0; padding: 0; }
              img { width: 100%; height: 100%; object-fit: contain; }
            }
            body {
              margin: 0;
              padding: 20px;
              display: flex;
              justify-content: center;
              align-items: center;
              min-height: 100vh;
              background: #f5f5f5;
            }
            img {
              max-width: 100%;
              max-height: 90vh;
              box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .no-print { display: block; text-align: center; margin-bottom: 20px; }
            @media print {
              .no-print { display: none; }
            }
          </style>
        </head>
        <body>
          <div>
            <div class="no-print">
              <h2>Photo Print Preview</h2>
              <p>Click Ctrl+P (or Cmd+P on Mac) to print this photo</p>
            </div>
            <img src="${response.data.download_url}" alt="Photo to print" onload="window.print()" />
          </div>
        </body>
        </html>
      `)
      printWindow.document.close()

      emit('print', response.data)
    } else {
      throw new Error('No download URL received')
    }

  } catch (error) {
    console.error('Print failed:', error)
    errorMessage.value = error.response?.data?.error || 'Print failed. Please try again.'
  } finally {
    isPrinting.value = false
  }
}

function handleNewSession() {
  emit('new-session')
}

function onQRError() {
  console.warn('QR code failed to load')
}
</script>

<style scoped>
.download-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.print-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

.success-animation {
  animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
