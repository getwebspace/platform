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
    
    let script,
        loader = () => {
            if (list.length) {
                let script = document.createElement('script');
                    script.src = list.shift();
                    script.onload = loader;
        
                document.body.appendChild(script);
            }
        };
    
    loader();
})();
