JSXS
========

Another Javascript compressor, written in PHP.

Test online at [http://www.xorax.info/labs/jsxs/](http://www.xorax.info/labs/jsxs/)


JSXS is in BETA test since 1 april 2009. Its possible the output compressed code not be a comptatible javascript code. Your browser can return a syntax error after the evaluation of it.

## Requirement

JSXS requires a minimum of php 5.1.0 . works on all plateforms.

## Usage

See [example file](https://github.com/xxorax/JSXS/blob/master/example.php).

## Options

- compatibility :
add semi-colons at the end of block (like function, object...) if necessary. Currently, works only if shrink option is actived.
- reduce :
removes blank spaces and comments.
- shrink :
reduces the names of variables to shorter.
- concatString :
all strings concatenated with the operator of addition are merged.

## Features

* adding semicolumn if necessary :
In javascript language, the end of line can be interpreted as a semicolumn, so your code without blankspace is probably wrong. This feature adds semicolumns for prevent syntax error.

* remove multiple and unused semicolumn :
Multiple ending semicolumn, and semicolumn before ending block are removed, except in for statements.
* remove blankspace and endline :
simple and usefull.
* reduce variable names :
The names of variable in non-global context are reduced to the smallest possible name, with none conflict. It's the same with function names.
* concat string :
Two string separated by + can be concatened.

## Example

Uncompressed :

``` javascript
function test (arg1) {
  var func = function (arg1) {
    return arg1+2;
  }
  return 'your value' + ' : ' + arg1 + func(arg1);
}
```

Compressed :

``` javascript
function test(b){var c=function(a){return a+2};return'your value : '+b+c(b)}
```

Result explained:

- removed white space.
- concatened string : 'your value' + ' : ' => 'your value : '.
- reduced variable names : arg1 => b, func => c, arg1 => a.
- removed semicolumn : ...func(arg1); => ...c(b).
- adding semicolumn for compatibility "} return 'your val..." => "};return'your val...".