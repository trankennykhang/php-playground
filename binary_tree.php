<?php

class TreeNode {
    public $value;
    public $left;
    public $right;

    public function __construct($value) {
        $this->value = $value;
        $this->left = null;
        $this->right = null;
    }
}

class BinaryTree {
    public $root;

    public function __construct() {
        $this->root = null;
    }

    public function insert($value) {
        $newNode = new TreeNode($value);
        if ($this->root === null) {
            $this->root = $newNode;
        } else {
            $this->insertNode($this->root, $newNode);
        }
    }

    private function insertNode($node, $newNode) {
        if ($newNode->value < $node->value) {
            if ($node->left === null) {
                $node->left = $newNode;
            } else {
                $this->insertNode($node->left, $newNode);
            }
        } else {
            if ($node->right === null) {
                $node->right = $newNode;
            } else {
                $this->insertNode($node->right, $newNode);
            }
        }
    }

    public function inOrderTraversal($node) {
        if ($node !== null) {
            $this->inOrderTraversal($node->left);
            echo $node->value . " ";
            $this->inOrderTraversal($node->right);
        }
    }
}

// Example usage
$tree = new BinaryTree();
$tree->insert(10);
$tree->insert(5);
$tree->insert(15);
$tree->insert(3);
$tree->insert(7);

$tree->inOrderTraversal($tree->root); // Output: 3 5 7 10 15
