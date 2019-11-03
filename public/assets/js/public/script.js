(() => {
    let
        date = new Date(),
        stamp = (date.getHours() * 100) + (date.getMinutes() * 10) + date.getSeconds(),
        
        list = [
            'polyfills.js',
            'catalog.js?',
        ];
    
    list.forEach((file) => {
        let script = document.createElement('script');
            script.src = '/assets/js/public/' + file;
        
        document.head.appendChild(script);
    });
})();
