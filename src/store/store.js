/* eslint-disable no-console */
// The store script handles app wide variables (or state), for the use of these variables and there governing concepts read the design.md
import { createPinia } from 'pinia'

import { useObjectStore } from './modules/object.js'
import { useNavigationStore } from './modules/navigation.js'
import { useSearchStore } from './modules/search.js'

const pinia = createPinia()

// Initialize stores
const objectStore = useObjectStore(pinia)
const navigationStore = useNavigationStore(pinia)
const searchStore = useSearchStore(pinia)

// Export initialized stores
export {
	objectStore,
	navigationStore,
	searchStore,
}

// Export pinia instance
export default pinia
