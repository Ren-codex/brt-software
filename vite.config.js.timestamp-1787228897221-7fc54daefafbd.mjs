// vite.config.js
import { defineConfig } from "file:///C:/Herd/brt-software/node_modules/vite/dist/node/index.js";
import laravel from "file:///C:/Herd/brt-software/node_modules/laravel-vite-plugin/dist/index.js";
import vue from "file:///C:/Herd/brt-software/node_modules/@vitejs/plugin-vue/dist/index.mjs";
var vite_config_default = defineConfig({
  build: {
    chunkSizeWarningLimit: 4e3
  },
  server: {
    // Without an explicit host, Node resolves localhost to the IPv6 loopback
    // and laravel-vite-plugin writes "http://[::1]:5173" into public/hot,
    // which browsers refuse to load ES modules from. Pin IPv4 instead.
    host: "127.0.0.1",
    port: 5173
  },
  plugins: [
    laravel({
      input: "resources/js/app.js",
      refresh: true
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false
        }
      }
    })
  ],
  resolve: {
    alias: {
      "@assets": "/public/",
      // Update this with the correct path to your images
      "@favicon": "/public/images/"
      // Update this with the correct path to your images
    }
  }
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJDOlxcXFxIZXJkXFxcXGJydC1zb2Z0d2FyZVwiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9maWxlbmFtZSA9IFwiQzpcXFxcSGVyZFxcXFxicnQtc29mdHdhcmVcXFxcdml0ZS5jb25maWcuanNcIjtjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfaW1wb3J0X21ldGFfdXJsID0gXCJmaWxlOi8vL0M6L0hlcmQvYnJ0LXNvZnR3YXJlL3ZpdGUuY29uZmlnLmpzXCI7aW1wb3J0IHsgZGVmaW5lQ29uZmlnIH0gZnJvbSAndml0ZSc7XG5pbXBvcnQgbGFyYXZlbCBmcm9tICdsYXJhdmVsLXZpdGUtcGx1Z2luJztcbmltcG9ydCB2dWUgZnJvbSAnQHZpdGVqcy9wbHVnaW4tdnVlJztcblxuZXhwb3J0IGRlZmF1bHQgZGVmaW5lQ29uZmlnKHtcbiAgICBidWlsZDoge1xuICAgICAgICBjaHVua1NpemVXYXJuaW5nTGltaXQ6IDQwMDAsXG4gICAgfSxcbiAgICBzZXJ2ZXI6IHtcbiAgICAgICAgLy8gV2l0aG91dCBhbiBleHBsaWNpdCBob3N0LCBOb2RlIHJlc29sdmVzIGxvY2FsaG9zdCB0byB0aGUgSVB2NiBsb29wYmFja1xuICAgICAgICAvLyBhbmQgbGFyYXZlbC12aXRlLXBsdWdpbiB3cml0ZXMgXCJodHRwOi8vWzo6MV06NTE3M1wiIGludG8gcHVibGljL2hvdCxcbiAgICAgICAgLy8gd2hpY2ggYnJvd3NlcnMgcmVmdXNlIHRvIGxvYWQgRVMgbW9kdWxlcyBmcm9tLiBQaW4gSVB2NCBpbnN0ZWFkLlxuICAgICAgICBob3N0OiAnMTI3LjAuMC4xJyxcbiAgICAgICAgcG9ydDogNTE3MyxcbiAgICB9LFxuICAgIHBsdWdpbnM6IFtcbiAgICAgICAgbGFyYXZlbCh7XG4gICAgICAgICAgICBpbnB1dDogJ3Jlc291cmNlcy9qcy9hcHAuanMnLFxuICAgICAgICAgICAgcmVmcmVzaDogdHJ1ZSxcbiAgICAgICAgfSksXG4gICAgICAgIHZ1ZSh7XG4gICAgICAgICAgICB0ZW1wbGF0ZToge1xuICAgICAgICAgICAgICAgIHRyYW5zZm9ybUFzc2V0VXJsczoge1xuICAgICAgICAgICAgICAgICAgICBiYXNlOiBudWxsLFxuICAgICAgICAgICAgICAgICAgICBpbmNsdWRlQWJzb2x1dGU6IGZhbHNlLFxuICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICB9LFxuICAgICAgICB9KSxcbiAgICBdLFxuICAgIHJlc29sdmU6IHtcbiAgICAgICAgYWxpYXM6IHtcbiAgICAgICAgICAgICdAYXNzZXRzJzogJy9wdWJsaWMvJywgLy8gVXBkYXRlIHRoaXMgd2l0aCB0aGUgY29ycmVjdCBwYXRoIHRvIHlvdXIgaW1hZ2VzXG4gICAgICAgICAgICAnQGZhdmljb24nOiAnL3B1YmxpYy9pbWFnZXMvJywgLy8gVXBkYXRlIHRoaXMgd2l0aCB0aGUgY29ycmVjdCBwYXRoIHRvIHlvdXIgaW1hZ2VzXG4gICAgICAgIH0sXG4gICAgfSxcbn0pO1xuIl0sCiAgIm1hcHBpbmdzIjogIjtBQUFvUCxTQUFTLG9CQUFvQjtBQUNqUixPQUFPLGFBQWE7QUFDcEIsT0FBTyxTQUFTO0FBRWhCLElBQU8sc0JBQVEsYUFBYTtBQUFBLEVBQ3hCLE9BQU87QUFBQSxJQUNILHVCQUF1QjtBQUFBLEVBQzNCO0FBQUEsRUFDQSxRQUFRO0FBQUE7QUFBQTtBQUFBO0FBQUEsSUFJSixNQUFNO0FBQUEsSUFDTixNQUFNO0FBQUEsRUFDVjtBQUFBLEVBQ0EsU0FBUztBQUFBLElBQ0wsUUFBUTtBQUFBLE1BQ0osT0FBTztBQUFBLE1BQ1AsU0FBUztBQUFBLElBQ2IsQ0FBQztBQUFBLElBQ0QsSUFBSTtBQUFBLE1BQ0EsVUFBVTtBQUFBLFFBQ04sb0JBQW9CO0FBQUEsVUFDaEIsTUFBTTtBQUFBLFVBQ04saUJBQWlCO0FBQUEsUUFDckI7QUFBQSxNQUNKO0FBQUEsSUFDSixDQUFDO0FBQUEsRUFDTDtBQUFBLEVBQ0EsU0FBUztBQUFBLElBQ0wsT0FBTztBQUFBLE1BQ0gsV0FBVztBQUFBO0FBQUEsTUFDWCxZQUFZO0FBQUE7QUFBQSxJQUNoQjtBQUFBLEVBQ0o7QUFDSixDQUFDOyIsCiAgIm5hbWVzIjogW10KfQo=
