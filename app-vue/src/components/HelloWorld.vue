<script setup>
import { ref, onMounted } from 'vue'
import Company from '@/assets/logo.svg'

const domain = ref('')
const isLoading = ref(true)
const isCopied = ref(false)
const isAnimating = ref(false)
const copyError = ref(false)

const getPortalDomain = () => {
  if (typeof BX24 !== 'undefined' && typeof BX24.getDomain === 'function') {
    return BX24.getDomain()
  }
  return window.location.hostname || 'your-portal.bitrix24.ru'
}

const fetchDomain = () => {
  try {
    domain.value = getPortalDomain()
  } catch (error) {
    console.error('Ошибка при получении домена:', error)
    domain.value = 'your-portal.bitrix24.ru'
  } finally {
    isLoading.value = false
  }
}

const copyDomain = () => {
  if (!domain.value) return
  
  if (navigator.clipboard) {
    navigator.clipboard.writeText(domain.value)
      .then(() => {
        handleCopySuccess()
      })
      .catch(err => {
        console.error('Ошибка при копировании через Clipboard API:', err)
        fallbackCopyText(domain.value)
      })
  } else {
    fallbackCopyText(domain.value)
  }
}

const fallbackCopyText = (text) => {
  const textArea = document.createElement('textarea')
  textArea.value = text
  textArea.style.position = 'fixed' 
  document.body.appendChild(textArea)
  textArea.focus()
  textArea.select()
  
  try {
    const successful = document.execCommand('copy')
    if (successful) {
      handleCopySuccess()
    } else {
      handleCopyError()
    }
  } catch (err) {
    console.error('Ошибка при fallback копировании:', err)
    handleCopyError()
  } finally {
    document.body.removeChild(textArea)
  }
}

const handleCopySuccess = () => {
  isCopied.value = true
  isAnimating.value = true
  copyError.value = false
  setTimeout(() => {
    isCopied.value = false
    isAnimating.value = false
  }, 2000)
}

const handleCopyError = () => {
  copyError.value = true
  setTimeout(() => {
    copyError.value = false
  }, 2000)
}

const openSite = () => {
  if (domain.value) {
    window.open(`https://${domain.value}`, '_blank')
  }
}

onMounted(() => {
  fetchDomain()
})
</script>

<template>
  <div class="wrapper">
    <!-- Логотип и заголовок над domain-container -->
    <div class="top-section">
      <div class="vue-logo-container">
        <img 
          alt="Company Logo" 
          class="logo" 
          :src="Company" 
          width="180" 
          height="125" 
        />
      </div>
      
      <div class="header">
        <h1 class="title">Коннектор Битрикс24</h1>
      </div>
    </div>

    <!-- Основная форма с левым выравниванием -->
    <div class="domain-container">
      <div class="domain-card">
        <div class="domain-content">
          <div class="domain-info">
            <h3>Ваш домен Bitrix24</h3>
            <div class="domain-value">
              <template v-if="isLoading">
                <div class="loading-spinner"></div>
              </template>
              <template v-else>
                <code>{{ domain }}</code>
              </template>
            </div>
            <p class="instruction">Скопируйте ваш домен и вставьте в приложение 1с-бус</p>
            <p v-if="copyError" class="error-message">
              Не удалось скопировать. Пожалуйста, скопируйте вручную.
            </p>
          </div>
        </div>
        
        <div class="domain-actions">
          <button 
            @click="copyDomain"
            :disabled="isLoading || !domain"
            class="action-btn copy-btn"
            :class="{ copied: isCopied, animate: isAnimating, 'error-btn': copyError }"
          >
            {{ isCopied ? 'Скопировано!' : copyError ? 'Ошибка!' : 'Копировать домен' }}
          </button>
          
          <button 
            @click="openSite"
            :disabled="isLoading || !domain"
            class="action-btn open-btn"
          >
            Перейти на портал
          </button>
        </div>
      </div>
    </div>

    <!-- Обращение в поддержку под domain-container -->
    <div class="footer">
      <a href="https://t.me/Postsales_B24" target="_blank">Нужна помощь? Обратитесь в нашу поддержку</a>
    </div>
  </div>
</template>

<style scoped>
.wrapper {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
  padding: 2rem;
  width: 600px;
  margin: 0 auto;
}

.top-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
}

.vue-logo-container {
  margin-bottom: 1rem;
}

.header {
  text-align: center;
  width: 100%;
}

.title {
  color: #2c3e50;
  font-size: 2rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.domain-container {
  width: 100%;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border: 1px solid #e0e0e0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.domain-card {
  padding: 1.5rem;
  text-align: left;
}

.domain-content {
  margin-bottom: 2rem;
}

.domain-info h3 {
  color: #2c3e50;
  font-size: 1.2rem;
  margin-bottom: 0.5rem;
  text-align: left;
}

.domain-value {
  background: #f2f2f2;
  padding: 0.8rem 1rem;
  border-radius: 8px;
  margin-bottom: 0.5rem;
  min-height: 24px;
  display: flex;
  justify-content: flex-start;
}

.domain-value code {
  font-family: 'Courier New', Courier, monospace;
  color: #0952C9;
  font-weight: 600;
  font-size: 1.1rem;
}

.instruction {
  color: #7f8c8d;
  font-size: 0.9rem;
  margin-top: 0.5rem;
  text-align: left;
}

.error-message {
  color: #ff4444;
  font-size: 0.9rem;
  margin-top: 0.5rem;
  text-align: left;
}

.domain-actions {
  display: flex;
  gap: 1rem;
  justify-content: space-around;
}

.action-btn {
  padding: 0.8rem 1.5rem;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  text-align: center;
  min-width: 230px;
}

.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.copy-btn {
  background: #0952C9;
  color: white;
}

.copy-btn:hover:not(:disabled) {
  background: #3aa876;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(66, 185, 131, 0.3);
}

.copy-btn.copied {
  background: #4caf50;
}

.copy-btn.error-btn {
  background: #ff4444;
}

.copy-btn.animate::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.3);
  transform: translateX(-100%);
  animation: shine 1s;
}

.open-btn {
  background: white;
  color: #0952C9;
  border: 1px solid #0952C9;
}

.open-btn:hover:not(:disabled) {
  background: #f0faf5;
  transform: translateY(-2px);
}

.footer {
  text-align: center;
  color: #95a5a6 !important;
  font-size: 0.9rem;
  margin-top: 1rem;
  width: 100%;
}
.footer a:hover {
  color: #0952C9 !important;
}

.loading-spinner {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 3px solid rgba(66, 185, 131, 0.3);
  border-radius: 50%;
  border-top-color: #0952C9;
  animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes shine {
  100% {
    transform: translateX(100%);
  }
}
</style>