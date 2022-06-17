// load resources once
if (window.loader === undefined) {
    window.loader = async function (list) {
        for (let url of list) {
            await window.loader.load(url);
        }
    }
    window.loader.loaded = new Set();
    window.loader.load = url => {
        return new Promise(resolve => {
            if (window.loader.loaded.has(url)) {
                return resolve();
            }
            
            let el;
            switch (true) {
                case url.endsWith('.css') || url.includes('.css'): {
                    el = document.createElement('link');
                    el.rel = 'stylesheet';
                    el.href = url;
                    el.onload = () => setTimeout(resolve, 30);
                    break;
                }
                case url.endsWith('.js') || url.includes('.js'): {
                    el = document.createElement('script');
                    el.src = url;
                    el.onload = () => setTimeout(resolve, 30);
                    break;
                }
            }
            
            document.body.appendChild(el);
            window.loader.loaded.add(url);
        });
    };
}

// ****
// Polyfill section
// ****

// get random float with min & max
if (Math.randomFloat === undefined) {
    Math.randomFloat = (min, max) => {
        return Math.random() * (max - min) + min;
    }
}

// get random int with min & max
if (Math.randomInt === undefined) {
    Math.randomInt = (min, max) => {
        min = Math.ceil(min);
        max = Math.floor(max);
        
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }
}

// default empty query list
if (location.query === undefined) {
    location.query = {};
    
    // build query params
    window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, (m, key, value) => {
        location.query[key] = value;
    });
}
