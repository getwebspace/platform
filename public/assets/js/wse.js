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
                case url.includes('.js'): {
                    el = document.createElement('script');
                    el.src = url;
                    el.onload = () => setTimeout(resolve, 30);
                    break;
                }
                case url.includes('.css'): {
                    el = document.createElement('link');
                    el.rel = 'stylesheet';
                    el.href = url;
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


// returns the plural ending of a word based on the number and array of endings
if (String.prototype.eos === undefined && Number.prototype.eos === undefined) {
    String.prototype.eos = Number.prototype.eos = function (one, two, five) {
        if (!isNaN(+this)) {
            let value = this % 100;
            
            if (value >= 11 && value <= 19) {
                return five;
            } else {
                value = this % 10;
                
                switch (value) {
                    case (1):
                        return one;
                    case (2):
                    case (3):
                    case (4):
                        return two;
                    default:
                        return five;
                }
            }
        }
        
        return this;
    }
}

// equals objects
if (Object.equals === undefined) {
    Object.equals = function (x, y) {
        if (x === y) {
            return true;
        }
        if (!(x instanceof Object) || !(y instanceof Object)) {
            return false;
        }
        if (x.constructor !== y.constructor) {
            return false;
        }
        
        for (var p in x) {
            if (!x.hasOwnProperty(p)) {
                continue;
            }
            if (!y.hasOwnProperty(p)) {
                return false;
            }
            if (x[p] === y[p]) {
                continue;
            }
            if (typeof (x[p]) !== "object") {
                return false;
            }
            if (!Object.equals(x[p], y[p])) {
                return false;
            }
        }
        for (p in y) {
            if (y.hasOwnProperty(p) && !x.hasOwnProperty(p)) {
                return false;
            }
        }
        
        return true;
    }
}
