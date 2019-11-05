(() => {
    let
        date = new Date(),
        stamp = (date.getHours() * 100) + (date.getMinutes() * 10) + date.getSeconds(),
        
        list = [
            // libs
            '/libs/simpleCart.min.js',
            '/libs/sweetalert2.all.min.js',
            
            // plugins
            '/plugins/catalog.js',
            '/plugins/polyfills.js',
        ];
    
    list.forEach((file) => {
        let script = document.createElement('script');
            script.src = '/assets/js/public' + file;
        
        document.body.appendChild(script);
    });
})();
