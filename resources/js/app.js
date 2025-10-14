import './bootstrap';
import '../css/chat-modal.css';
import './chat-modal';
import jQuery from 'jquery';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.$ = window.jQuery = jQuery; // Make jQuery globally available

Alpine.start();

import './items/datatable_init'; // Import datatable_init.js
import './items/item_given'; // Import item_given.js
import './items/item_delete'; // Import item_delete.js
import './items/item_in_local_storage'; // Import item_in_local_storage.js