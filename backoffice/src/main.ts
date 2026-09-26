import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { registerAppSw } from './utils/sw'
import './styles/main.css'

registerAppSw()

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')