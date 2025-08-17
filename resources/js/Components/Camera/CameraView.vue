<template>
  <div class="camera-view relative">
    <!-- Camera Stream -->
    <div class="camera-container relative bg-black rounded-lg overflow-hidden">
      <video
        ref="videoRef"
        autoplay
        muted
        playsinline
        class="w-full h-full object-cover"
        :class="{ 'mirror': isFrontCamera }"
      />
      
      <!-- Camera Overlay -->
      <div class="absolute inset-0 pointer-events-none">
        <!-- Viewfinder Grid -->
        <div v-if="showGrid" class="absolute inset-0 grid grid-cols-3 grid-rows-3 opacity-30">
          <div v-for="i in 9" :key="i" class="border border-white/50"></div>
        </div>
        
        <!-- Countdown Overlay -->
        <div v-if="countdown > 0" class="absolute inset-0 flex items-center justify-center bg-black/50">
          <div class="text-center">
            <div class="text-white text-8xl font-bold animate-pulse mb-4">{{ countdown }}</div>
            <div v-if="props.continuousMode" class="text-white text-xl">
              Photo {{ photosTaken + 1 }} of {{ props.maxPhotos }}
            </div>
          </div>
        </div>
        
        <!-- Continuous Mode Status -->
        <div v-if="props.continuousMode && isContinuousRunning && countdown === 0" 
             class="absolute top-4 left-4 bg-red-500 text-white px-4 py-2 rounded-full font-semibold animate-pulse">
          🔴 LIVE - Photo {{ photosTaken + 1 }}/{{ props.maxPhotos }}
        </div>
        
        <!-- Aspect Ratio Guide -->
        <div v-if="aspectRatio && !isCapturing" class="absolute inset-4">
          <div class="border-2 border-white/70 border-dashed h-full w-full flex items-center justify-center"
               :style="aspectRatioStyle">
            <span class="text-white/70 text-sm">{{ aspectRatio }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Camera Controls -->
    <div class="camera-controls mt-6 flex flex-col items-center space-y-4">
      <!-- Device Selection -->
      <div v-if="devices.length > 1" class="flex items-center space-x-2">
        <label class="text-sm font-medium">Camera:</label>
        <select
          v-model="selectedDeviceId"
          @change="switchCamera"
          class="px-3 py-1 border border-gray-300 rounded-md text-sm"
        >
          <option v-for="device in devices" :key="device.deviceId" :value="device.deviceId">
            {{ device.label || `Camera ${devices.indexOf(device) + 1}` }}
          </option>
        </select>
      </div>

      <!-- Resolution Selection -->
      <div class="flex items-center space-x-2">
        <label class="text-sm font-medium">Quality:</label>
        <select
          v-model="selectedResolution"
          @change="updateResolution"
          class="px-3 py-1 border border-gray-300 rounded-md text-sm"
        >
          <option v-for="res in resolutionOptions" :key="res.label" :value="res">
            {{ res.label }}
          </option>
        </select>
      </div>

      <!-- Capture Controls -->
      <div class="flex items-center justify-center space-x-4">
        <!-- Manual Mode Capture Button -->
        <button
          v-if="!props.continuousMode"
          @click="capturePhoto"
          :disabled="!isReady || isCapturing"
          class="capture-button w-16 h-16 bg-red-500 hover:bg-red-600 disabled:bg-gray-300 
                 rounded-full border-4 border-white shadow-lg transition-colors flex items-center justify-center"
        >
          <div class="w-12 h-12 bg-white rounded-full"></div>
        </button>
        
        <!-- Continuous Mode Controls -->
        <div v-if="props.continuousMode" class="flex items-center space-x-4">
          <button
            v-if="!isContinuousRunning"
            @click="startContinuousCapture"
            :disabled="!isReady || isCapturing || photosTaken >= props.maxPhotos"
            class="px-6 py-3 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 
                   text-white rounded-lg font-semibold transition-colors"
          >
            🚀 Start Rapid Capture
          </button>
          
          <button
            v-if="isContinuousRunning"
            @click="stopContinuousCapture"
            class="px-6 py-3 bg-red-500 hover:bg-red-600 
                   text-white rounded-lg font-semibold transition-colors animate-pulse"
          >
            ⏹️ Stop Capture
          </button>
          
          <button
            v-if="!isContinuousRunning && photosTaken > 0"
            @click="finishContinuous"
            class="px-6 py-3 bg-green-500 hover:bg-green-600 
                   text-white rounded-lg font-semibold transition-colors"
          >
            ✅ Finish ({{ photosTaken }} photos)
          </button>
        </div>
        
        <button
          @click="toggleGrid"
          class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md text-sm"
        >
          {{ showGrid ? 'Hide Grid' : 'Show Grid' }}
        </button>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md">
      {{ error }}
    </div>

    <!-- Hidden Canvas for Capture -->
    <canvas ref="canvasRef" class="hidden"></canvas>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  aspectRatio: {
    type: String,
    default: null // e.g., "4:3", "16:9"
  },
  continuousMode: {
    type: Boolean,
    default: false
  },
  maxPhotos: {
    type: Number,
    default: 6
  },
  currentPhotoCount: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['photo-captured', 'camera-ready', 'camera-error', 'continuous-complete'])

// Refs
const videoRef = ref(null)
const canvasRef = ref(null)
const stream = ref(null)
const devices = ref([])
const selectedDeviceId = ref(null)
const selectedResolution = ref({ width: 1920, height: 1080, label: 'HD (1920×1080)' })
const isReady = ref(false)
const isCapturing = ref(false)
const countdown = ref(0)
const showGrid = ref(false)
const error = ref(null)
const isContinuousRunning = ref(false)
const continuousInterval = ref(null)
const photosTaken = ref(0)

// Resolution options
const resolutionOptions = ref([
  { width: 1280, height: 720, label: 'HD (1280×720)' },
  { width: 1920, height: 1080, label: 'Full HD (1920×1080)' },
  { width: 2560, height: 1440, label: '2K (2560×1440)' },
  { width: 3840, height: 2160, label: '4K (3840×2160)' }
])

// Computed
const isFrontCamera = computed(() => {
  const device = devices.value.find(d => d.deviceId === selectedDeviceId.value)
  return device?.label?.toLowerCase().includes('front') || false
})

const aspectRatioStyle = computed(() => {
  if (!props.aspectRatio) return {}
  
  const [width, height] = props.aspectRatio.split(':').map(Number)
  const ratio = width / height
  
  return {
    aspectRatio: `${width} / ${height}`,
    maxWidth: '100%',
    maxHeight: '100%',
    margin: 'auto'
  }
})

// Methods
async function initializeCamera() {
  try {
    error.value = null
    
    // Check if browser supports getUserMedia
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error('Camera access is not supported in this browser')
    }

    // Get available devices
    await getDevices()
    
    // Start camera stream
    await startCamera()
    
  } catch (err) {
    console.error('Camera initialization error:', err)
    error.value = err.message
    emit('camera-error', err.message)
  }
}

async function getDevices() {
  try {
    const deviceList = await navigator.mediaDevices.enumerateDevices()
    devices.value = deviceList.filter(device => device.kind === 'videoinput')
    
    if (devices.value.length === 0) {
      throw new Error('No camera devices found')
    }
    
    // Select first camera if none selected
    if (!selectedDeviceId.value) {
      selectedDeviceId.value = devices.value[0].deviceId
    }
  } catch (err) {
    throw new Error('Failed to access camera devices: ' + err.message)
  }
}

async function startCamera() {
  try {
    // Stop existing stream
    if (stream.value) {
      stream.value.getTracks().forEach(track => track.stop())
    }

    const constraints = {
      video: {
        deviceId: selectedDeviceId.value ? { exact: selectedDeviceId.value } : undefined,
        width: { ideal: selectedResolution.value.width },
        height: { ideal: selectedResolution.value.height },
        facingMode: { ideal: 'environment' } // Prefer back camera
      },
      audio: false
    }

    stream.value = await navigator.mediaDevices.getUserMedia(constraints)
    
    if (videoRef.value) {
      videoRef.value.srcObject = stream.value
      
      videoRef.value.onloadedmetadata = () => {
        isReady.value = true
        emit('camera-ready')
      }
    }
  } catch (err) {
    throw new Error('Failed to start camera: ' + err.message)
  }
}

async function switchCamera() {
  await startCamera()
}

async function updateResolution() {
  await startCamera()
}

async function capturePhoto() {
  if (!isReady.value || isCapturing.value) return
  
  try {
    isCapturing.value = true
    
    // Countdown (shorter for continuous mode)
    const countdownTime = props.continuousMode ? 2 : 3
    for (let i = countdownTime; i > 0; i--) {
      countdown.value = i
      await new Promise(resolve => setTimeout(resolve, 1000))
    }
    countdown.value = 0
    
    // Play shutter sound
    playShutterSound()
    
    // Capture frame
    const canvas = canvasRef.value
    const video = videoRef.value
    const context = canvas.getContext('2d')
    
    // Set canvas dimensions
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    
    // Draw video frame to canvas
    context.drawImage(video, 0, 0, canvas.width, canvas.height)
    
    // Apply aspect ratio cropping if specified
    let finalCanvas = canvas
    if (props.aspectRatio) {
      finalCanvas = cropToAspectRatio(canvas, props.aspectRatio)
    }
    
    // Convert to blob
    finalCanvas.toBlob(blob => {
      if (blob) {
        emit('photo-captured', {
          blob,
          width: finalCanvas.width,
          height: finalCanvas.height,
          aspectRatio: props.aspectRatio
        })
        
        // Update photo count for continuous mode
        if (props.continuousMode) {
          photosTaken.value++
        }
      }
    }, 'image/jpeg', 0.92)
    
  } catch (err) {
    console.error('Capture error:', err)
    error.value = 'Failed to capture photo: ' + err.message
  } finally {
    isCapturing.value = false
  }
}

async function startContinuousCapture() {
  if (!isReady.value || isContinuousRunning.value) return
  
  isContinuousRunning.value = true
  photosTaken.value = props.currentPhotoCount || 0
  
  // Initial capture
  await capturePhoto()
  
  // Set up interval for continuous capture
  continuousInterval.value = setInterval(async () => {
    if (photosTaken.value >= props.maxPhotos) {
      stopContinuousCapture()
      return
    }
    
    if (!isCapturing.value) {
      await capturePhoto()
    }
  }, 4000) // 4 seconds between photos (2s countdown + 2s processing buffer)
}

function stopContinuousCapture() {
  isContinuousRunning.value = false
  if (continuousInterval.value) {
    clearInterval(continuousInterval.value)
    continuousInterval.value = null
  }
  countdown.value = 0
  isCapturing.value = false
}

function finishContinuous() {
  stopContinuousCapture()
  emit('continuous-complete')
}

function cropToAspectRatio(canvas, aspectRatio) {
  const [targetWidth, targetHeight] = aspectRatio.split(':').map(Number)
  const targetRatio = targetWidth / targetHeight
  
  const sourceWidth = canvas.width
  const sourceHeight = canvas.height
  const sourceRatio = sourceWidth / sourceHeight
  
  let cropWidth, cropHeight, cropX, cropY
  
  if (sourceRatio > targetRatio) {
    // Source is wider than target, crop horizontally
    cropHeight = sourceHeight
    cropWidth = sourceHeight * targetRatio
    cropX = (sourceWidth - cropWidth) / 2
    cropY = 0
  } else {
    // Source is taller than target, crop vertically
    cropWidth = sourceWidth
    cropHeight = sourceWidth / targetRatio
    cropX = 0
    cropY = (sourceHeight - cropHeight) / 2
  }
  
  const croppedCanvas = document.createElement('canvas')
  croppedCanvas.width = cropWidth
  croppedCanvas.height = cropHeight
  
  const ctx = croppedCanvas.getContext('2d')
  ctx.drawImage(canvas, cropX, cropY, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight)
  
  return croppedCanvas
}

function playShutterSound() {
  // Create a brief audio context sound for shutter
  try {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)()
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()
    
    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)
    
    oscillator.frequency.value = 800
    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1)
    
    oscillator.start(audioContext.currentTime)
    oscillator.stop(audioContext.currentTime + 0.1)
  } catch (err) {
    // Ignore audio errors
  }
}

function toggleGrid() {
  showGrid.value = !showGrid.value
}

function stopCamera() {
  if (stream.value) {
    stream.value.getTracks().forEach(track => track.stop())
    stream.value = null
  }
  isReady.value = false
}

// Lifecycle
onMounted(() => {
  initializeCamera()
})

onUnmounted(() => {
  stopCamera()
  stopContinuousCapture()
})

// Watch for device permission changes
watch(() => selectedDeviceId.value, () => {
  if (selectedDeviceId.value) {
    startCamera()
  }
})

// Watch for continuous mode changes
watch(() => props.continuousMode, (newValue) => {
  if (!newValue) {
    stopContinuousCapture()
  }
})

// Watch for max photos reached in continuous mode
watch(() => photosTaken.value, (newCount) => {
  if (props.continuousMode && newCount >= props.maxPhotos) {
    stopContinuousCapture()
  }
})
</script>

<style scoped>
.camera-view {
  max-width: 600px;
  margin: 0 auto;
}

.camera-container {
  aspect-ratio: 4 / 3;
  min-height: 300px;
}

.mirror {
  transform: scaleX(-1);
}

.capture-button:active {
  transform: scale(0.95);
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}
</style>