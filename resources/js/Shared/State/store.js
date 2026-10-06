import Vuex from 'vuex';

import layout from './modules/layout';
import notification from './modules/layout';
import todo from './modules/todo';
import draft from './modules/draft';

const store = new Vuex.Store({
  modules: {
    layout: layout, // Register the layout module
    notification, // Register the notifications module
    todo,
    draft // A half-written form someone set aside

    // Add more modules as needed
  },
});

export default store;

