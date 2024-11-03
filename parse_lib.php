<?php
//instructions
$ipp_instructions = array(
    "MOVE",   //0
    "CREATEFRAME",
    "PUSHFRAME",
    "POPFRAME",
    "DEFVAR",
    "CALL",     //5
    "RETURN",
    "PUSHS",
    "POPS",
    "ADD",
    "SUB",      //10
    "MUL",
    "IDIV",
    "LT",
    "GT",
    "EQ",       //15
    "AND",
    "OR",
    "NOT",
    "INT2CHAR",
    "STRI2INT", //20
    "READ",
    "WRITE",
    "CONCAT",
    "STRLEN",
    "GETCHAR",  //25
    "SETCHAR",
    "TYPE",
    "LABEL",
    "JUMP",
    "JUMPIFEQ", //30
    "JUMPIFNEQ",
    "EXIT",
    "DPRINT",
    "BREAK"     //34
);

//tokens for lexer
const HEADER_TOK = 100;
const INSTRUCTION_TOK = 200;
const EOF_TOK = 999;
const CONST_TOK = 400;
const VARIABLE_TOK = 500;
const LABEL_TOK = 600;
const TYPE_TOK = 700;

//error codes
const MISSING_PARAM = 10;
const R_FILE_ERR = 11;
const WR_FILE_ERR = 12;
const MISSING_HEADER = 21;
const UNKNOWN_INVALID_OP_CODE = 22;
const OTHER_ERR = 23;
const INTERNAL_ERR = 99;

//Stats
$comments = 0;
$frequent = array();

$ipp_stats = array(
$loc = 0,           // 0
$comm = 0,          // 1
$labels = 0,        // 2
$jumps = 0,         // 3
$fwjumps = 0,       // 4
$backjumps = 0,     // 5
$badjumps = 0       // 6
);