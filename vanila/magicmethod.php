<?php
class test {
    public function __call($name, $arguments) {
        print $name;
    }
    public function callMethod(){
        print "123";
    }
    public static function getObject(){
        return new self();
    }
}
$b = test::getObject();
$b->callMethod();
//$a = new test();
//$a->abc();