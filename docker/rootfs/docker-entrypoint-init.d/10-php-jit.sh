#!/bin/sh
# opcache.jit from the PHP_JIT variable: tracing (default), function or off.
#
# The JIT compiles to the machine code of the CPU it runs on. Under emulation (an amd64 image on
# an arm64 host, QEMU or Rosetta) it can hang a request until max_execution_time, so such a
# container is started with PHP_JIT=off - or better, with the native arm64 image.
set -eu

case "${PHP_JIT:-tracing}" in
    tracing | function)
        mode="${PHP_JIT:-tracing}"
        size="100M"
        ;;
    off | 0 | false | disable | disabled)
        mode="off"
        size="0"
        ;;
    *)
        echo >&2 "PHP_JIT=${PHP_JIT} is not tracing, function or off: using tracing"
        mode="tracing"
        size="100M"
        ;;
esac

# read after custom.ini (file names go in alphabetical order), before php-fpm is started
printf 'opcache.jit = %s\nopcache.jit_buffer_size = %s\n' "$mode" "$size" > /usr/local/etc/php/conf.d/zz-jit.ini
echo "opcache.jit = $mode"
