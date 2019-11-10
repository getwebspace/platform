"use strict";

(() => {
    let
        date = new Date(),
        stamp = (date.getHours() * 100) + (date.getMinutes() * 10) + date.getSeconds(),
        
        list = [
            // libs
            '/assets/js/plugin/simpleCart/simpleCart.min.js',
            '/assets/js/plugin/sweetalert/sweetalert2.all.min.js',
            
            // plugins
            '/assets/js/plugin/polyfills.js',
            '/assets/js/public/catalog.js',
        ];
    
    list.forEach((file) => {
        let script = document.createElement('script');
            script.src = file;
        
        document.body.appendChild(script);
    });
})();
