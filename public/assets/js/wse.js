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
            window.loader.loaded.add(url);

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
        });
    };
}

if (window.debounce === undefined) {
    window.debounce = function (func, delay) {
        let timerId;

        return function () {
            let context = this, args = arguments;

            clearTimeout(timerId);

            timerId = setTimeout(() => func.apply(context, args), delay);
        };
    }
}

if (window.throttle === undefined) {
    window.throttle = function (func, delay) {
        let lastTime = 0;

        return function() {
            let currentTime = new Date().getTime();

            if (currentTime - lastTime >= delay) {
                func.apply(this, arguments);
                lastTime = currentTime;
            }
        };
    }
}

if (window.flatten === undefined && window.unflatten === undefined) {
    let isBuffer = (obj) => {
        return obj &&
            obj.constructor &&
            (typeof obj.constructor.isBuffer === 'function') &&
            obj.constructor.isBuffer(obj)
    }

    let keyIdentity = (key) => {
        return key
    }

    window.flatten = function (target, opts) {
        opts = opts || {}

        let delimiter = opts.delimiter || '.'
        let maxDepth = opts.maxDepth
        let transformKey = opts.transformKey || keyIdentity
        let output = {}

        function step (object, prev, currentDepth) {
            currentDepth = currentDepth || 1
            Object.keys(object).forEach(function (key) {
                let value = object[key]
                let isarray = opts.safe && Array.isArray(value)
                let type = Object.prototype.toString.call(value)
                let isbuffer = isBuffer(value)
                let isobject = (
                    type === '[object Object]' ||
                    type === '[object Array]'
                )

                let newKey = prev
                    ? prev + delimiter + transformKey(key)
                    : transformKey(key)

                if (!isarray && !isbuffer && isobject && Object.keys(value).length &&
                    (!opts.maxDepth || currentDepth < maxDepth)) {
                    return step(value, newKey, currentDepth + 1)
                }

                output[newKey] = value
            })
        }

        step(target)

        return output
    }

    window.unflatten = function (target, opts) {
        opts = opts || {}

        let delimiter = opts.delimiter || '.'
        let overwrite = opts.overwrite || false
        let transformKey = opts.transformKey || keyIdentity
        let result = {}

        let isbuffer = isBuffer(target)
        if (isbuffer || Object.prototype.toString.call(target) !== '[object Object]') {
            return target
        }

        // safely ensure that the key is
        // an integer.
        function getkey (key) {
            let parsedKey = Number(key)

            return (
                isNaN(parsedKey) ||
                key.indexOf('.') !== -1 ||
                opts.object
            )
                ? key
                : parsedKey
        }

        function addKeys (keyPrefix, recipient, target) {
            return Object.keys(target).reduce(function (result, key) {
                result[keyPrefix + delimiter + key] = target[key]

                return result
            }, recipient)
        }

        function isEmpty (val) {
            let type = Object.prototype.toString.call(val)
            let isArray = type === '[object Array]'
            let isObject = type === '[object Object]'

            if (!val) {
                return true
            } else if (isArray) {
                return !val.length
            } else if (isObject) {
                return !Object.keys(val).length
            }
        }

        target = Object.keys(target).reduce(function (result, key) {
            let type = Object.prototype.toString.call(target[key])
            let isObject = (type === '[object Object]' || type === '[object Array]')
            if (!isObject || isEmpty(target[key])) {
                result[key] = target[key]
                return result
            } else {
                return addKeys(
                    key,
                    result,
                    flatten(target[key], opts)
                )
            }
        }, {})

        Object.keys(target).forEach(function (key) {
            let split = key.split(delimiter).map(transformKey)
            let key1 = getkey(split.shift())
            let key2 = getkey(split[0])
            let recipient = result

            while (key2 !== undefined) {
                if (key1 === '__proto__') {
                    return
                }

                let type = Object.prototype.toString.call(recipient[key1])
                let isobject = (
                    type === '[object Object]' ||
                    type === '[object Array]'
                )

                // do not write over falsey, non-undefined values if overwrite is false
                if (!overwrite && !isobject && typeof recipient[key1] !== 'undefined') {
                    return
                }

                if ((overwrite && !isobject) || (!overwrite && recipient[key1] == null)) {
                    recipient[key1] = (
                        typeof key2 === 'number' &&
                        !opts.object
                            ? []
                            : {}
                    )
                }

                recipient = recipient[key1]
                if (split.length > 0) {
                    key1 = getkey(split.shift())
                    key2 = getkey(split[0])
                }
            }

            // unflatten again for 'messy objects'
            recipient[key1] = unflatten(target[key], opts)
        })

        return result
    }
}

if (window.formatNumber === undefined) {
    window.formatNumber = function (number, {decimalPlaces = 0, currencySymbolStart = '',  currencySymbolEnd = '', thousandsSeparator = '', decimalSeparator = ''}) {
        let roundedNumber = number.toFixed(decimalPlaces);
        let parts = roundedNumber.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSeparator);

        let formattedNumber = parts.join(decimalSeparator);

        if (currencySymbolStart || currencySymbolEnd) {
            formattedNumber = `${currencySymbolStart}${formattedNumber}${currencySymbolEnd}`.trim();
        }

        return formattedNumber;
    }
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
