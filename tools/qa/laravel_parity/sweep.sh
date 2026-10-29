#!/bin/bash
# Parity check of the Laravel port against the prototype, page by page.
# Needs: `php artisan serve --port=8123` (APP_URL=http://127.0.0.1:8123, content imported) in pressmedia_laravel.
# Usage (from prototype_html/): ../tools/qa/laravel_parity/sweep.sh fr/articles/*.html fr/societe.html ...
# Prints only pages whose normalised <main> differs; articles are expected to differ by the 9 lines of the
# server-backed comment form. One page in detail: python3 ../tools/qa/laravel_parity/htmldiff.py <path> [max lines]
D=$(dirname "$0")
for p in "$@"; do
  n=$(python3 "$D/htmldiff.py" "$p" 0 2>&1 | grep -o 'total diff lines [0-9]*' | grep -o '[0-9]*$')
  exp=0; [[ $p == */articles/* ]] && exp=9
  [ "${n:-ERR}" != "$exp" ] && echo "$p $n"
done
echo "checked $#"
