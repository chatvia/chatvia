import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useChatStore = defineStore('chat', () => {
    const isChatExpanded = ref(localStorage.getItem('isChatExpanded') === 'true')

    function toggleChatExpand() {
        isChatExpanded.value = !isChatExpanded.value
        localStorage.setItem('isChatExpanded', isChatExpanded.value.toString())
        return isChatExpanded.value
    }

    return {
        isChatExpanded,
        toggleChatExpand
    }
})
