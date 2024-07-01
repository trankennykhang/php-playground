<?php
class a {
    static $var = "a";
    public static function getName(){
        print "this is ".static::$var."\n";
    }
}
class b extends a {
    
}
a::getName();
b::getName();