<?php
/*
 * IPP 2023 PROJECT: PART 1
 *
 * File: parse.php
 * 
 * Author: Adam Pap, xpapad11
*/
ini_set('display_errors', 'stderr');
include_once('parse_lib.php');

$input = fopen('php://stdin', 'r');
$stderr = fopen('php://stderr', 'w');
$output = fopen('php://stdout', 'w');

//------------------------Parameter parsing------------------------
$index = 0; //Var which will tell us to which stats given param (--jumps, --badjumps ....) belongs
$params_for_stats = array(); //params for --stats e.g. --loc --comments ....
$stats_file = array();
$string = '';
$short_options = "";
$long_options = array("help", "stats", "loc", "comments", "labels", "jumps", "fwjumps", "backjumps", "badjumps", "print", "eol", "frequent");
$arg = getopt($short_options, $long_options);

if (array_key_exists("help", $arg) == true) {
    if ($argc > 2) {
        fwrite($stderr, "Zadali ste parameter --help spolu s inym parametrom.\n");
        exit(MISSING_PARAM);
    }
    echo "Tento skript nacita zdrojovy kod zapisany v IPPcode23, zkontroluje lexikalnu\n";
    echo "a syntakticku spravnost kodu a na standartny vystup (STDOUT) vypise XML reprezentaciu daneho kodu.\n";
    echo "\n";
    exit(0);
} else if (array_key_exists("stats", $arg) == true) {
    foreach ($argv as $key => $value) {
        $stats_params_general = explode('=', $value, 2); //extract the file name 

        if ($value != 'parse.php') {
            if (isset($stats_params_general[1]) &&  $stats_params_general[0] != '--stats' && $stats_params_general[0] != '--print') {
                fwrite($stderr, "Zadali ste parameter/parametre pre statistiku bez paramtru --stats=file.\n");
                exit(MISSING_PARAM);
            } else if (isset($stats_params_general[1]) && $stats_params_general[0] == '--stats') {
                $stats_params_general[0] = $stats_params_general[1]; //Change first and last elemnt in order to pop the last 
                array_pop($stats_params_general);
                array_push($stats_file, $stats_params_general,); //Save file names
                $index++; //Index to sort out which params belongs to which file with stats
            } else {
                array_push($stats_params_general, $index);
            }

            $bad_values = array("help", "stats"); //values we dont care about now
            if (!in_array($bad_values, $stats_params_general) && ($stats_file != $stats_params_general)) {
                array_push($params_for_stats, $stats_params_general);
            }
        }
    }
}

//-------------------Create XML generator using XMLWriter library-------------------
$xw = xmlwriter_open_memory();
xmlwriter_set_indent($xw, 1);
$res = xmlwriter_set_indent_string($xw, ' ');
xmlwriter_start_document($xw, '1.0', 'UTF-8');

//Start XML document with necessery headers  
xmlwriter_start_element($xw, 'program'); // <program>
xmlwriter_start_attribute($xw, 'language');
xmlwriter_text($xw, 'IPPcode23');
xmlwriter_end_attribute($xw);

$ipp_stats = syntax_chceck_xml_build($input, $output, $stderr, $ipp_instructions, $xw, $ipp_stats); //Syntax checking and XML building function
$ipp_stats[1] = $comments;

//-------------------Open files for stats and writedown statistics-------------------
$help_array = array("--loc" => 0, "--comments" => 1, "--labels" => 2, "--jumps" => 3, "--fwjumps" => 4, "--backjumps" => 5, "--badjumps" => 6, "--print" => 7, "--eol" => 8, "--frequent" => 9); //associative array to decide which param write to file
$j = 0; //counter for files
$print_chceck = 0; //chceck whether thwere is --print param
$k = 1; //counter with which we will decide which param belong to which file

if(isset($stats_params_general))
{
    foreach ($stats_params_general as $key => $value) { //For saving string
        if ($value == '--print') {
            $string = $stats_params_general[1];
        }
    }
}


while (isset($stats_file[$j][0])) {
    $file = $stats_file[$j][0];
    $pointer = fopen($file, 'w');

    $d = 0; //counter with which we will iterate through the params
    do {
        $d++;
        if (isset($params_for_stats[$d][0]) && $params_for_stats[$d][0] == "--frequent" && $k == $params_for_stats[$d][1]) {
            if (!arsort($frequent)) //sorting array - descending
            {
                fwrite($stderr, "Interna chyba skriptu.\n");
                exit(INTERNAL_ERR);
            }
            $frequent_end_elem = array_key_last($frequent);

            foreach ($frequent as $key => $value) {
                foreach ($ipp_instructions as $key2 => $value2) {
                    if ($key2 == $key) fwrite($pointer, $ipp_instructions[$key2]);
                }
                if ($frequent_end_elem != $key) fwrite($pointer, ",");
            }
        }

        if (isset($params_for_stats[$d][0]) && $params_for_stats[$d][0] == "--eol" && $k == $params_for_stats[$d][1]) //$k == $params_for_stats[$d][1] ---                                                                                                            
        {                                                                                                            //for chcecking to which file write down the parameter
            fwrite($pointer, "\n");
        }
        if (isset($params_for_stats[$d][0]) && $params_for_stats[$d][0] == "--print" && $k == $params_for_stats[$d][2]) {
            fwrite($pointer, $string);
            fwrite($pointer, "\n");
        }

        if (isset($params_for_stats[$d][1]) && $params_for_stats[$d][0] != "--eol" && $params_for_stats[$d][0] != "--frequent" && $k == $params_for_stats[$d][1]) {
            $param_to_file = $params_for_stats[$d][0];
            fwrite($pointer, $ipp_stats[$help_array[$param_to_file]] . "\n");
        }
    } while (isset($params_for_stats[$d]));
    fclose($pointer);
    $d++;
    $j++;
    $k++;
}
//---------------------------------------------------------------------------------

xmlwriter_end_element($xw); // END </program>
xmlwriter_end_document($xw); //END fo XML writer
echo xmlwriter_output_memory($xw);

//-------------------SYNTAX CHECK/XML CODE BUILD-------------------
function syntax_chceck_xml_build($input, $output, $stderr, $ipp_instructions, $xw, $ipp_stats)
{
    global $frequent; //array for frequent statistic
    $fwjumps = array();
    $syntax_analyzed_line = array();
    $instruction_count = 0; //Counter for "order" in XML code
    $syntax_analyzed_line = lexer($input, $output, $stderr, $ipp_instructions); //get the data

    //Check if there is a file header
    if ($syntax_analyzed_line[0][0] != HEADER_TOK) {
        fwrite($stderr, "Chyba hlavicka suboru.\n");
        exit(MISSING_HEADER);
    } else {
        while (true) {
            $instruction_count++;
            $syntax_analyzed_line = lexer($input, $output, $stderr, $ipp_instructions); // get the data

            if ($syntax_analyzed_line[0][0] == EOF_TOK) {
                break;
            } else {
                xmlwriter_start_element($xw, 'instruction'); // START <instruction>
                xmlwriter_start_attribute($xw, 'order');
                xmlwriter_text($xw, htmlspecialchars($instruction_count));
                xmlwriter_end_attribute($xw);
                xmlwriter_start_attribute($xw, 'opcode');
                xmlwriter_text($xw, htmlspecialchars($ipp_instructions[$syntax_analyzed_line[0][0]]));
                xmlwriter_end_attribute($xw);
                //----------------------------------------INSTRUCTION SWITCH----------------------------------------
                if ($syntax_analyzed_line[0][1] == INSTRUCTION_TOK || $syntax_analyzed_line[0][0] == INSTRUCTION_TOK) {
                    $ipp_stats[0]++; //STATS instructions count;
                    array_push($frequent, $syntax_analyzed_line[0][0]);

                    if ($syntax_analyzed_line[0][0] == 28) {
                        array_push($fwjumps, $syntax_analyzed_line[1][0]);
                        $ipp_stats[2]++; //STATS labels count;
                    }

                    if ($syntax_analyzed_line[0][0] == 29 || $syntax_analyzed_line[0][0] == 30 || $syntax_analyzed_line[0][0] == 31 || $syntax_analyzed_line[0][0] == 5) $ipp_stats[3]++; //STATS jumps count;

                    if (($syntax_analyzed_line[0][0] == 29 || $syntax_analyzed_line[0][0] == 30 || $syntax_analyzed_line[0][0] == 31)) //STATS fwjumps count;
                    {
                        $test = array_search($syntax_analyzed_line[1][0], $fwjumps); //chceck if the label is already in fwjumps field, if so run strincompare
                        if ($test == 0)                                               //and if it is the label we looking for, dont increment 
                        {
                            $test2 = strcasecmp($fwjumps[$test], $syntax_analyzed_line[1][0]);
                            if ($test2 != 0) {
                                $ipp_stats[4]++; //STATS fwjumps count;
                            }
                        }

                        $test = array_search($syntax_analyzed_line[1][0], $fwjumps); //chceck if the label is already in fwjumps field, if so run strincompare
                        if ($test >= 0)                                               //and if it is the label we looking for then increment
                        {
                            $test2 = strcasecmp($fwjumps[$test], $syntax_analyzed_line[1][0]);
                            if ($test2 == 0) {
                                $ipp_stats[5]++; //STATS backjumps count;
                            }
                        }
                    }

                    switch ($syntax_analyzed_line[0][0]) {

                            //NO PARAMS INSTRUCTIONS
                        case '1': case '2': case '3': case '6': case '34':
                            $type_of_instr = 0; //This will tell us if the instruction have 0, 1 or more arguments
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);
                            break;

                            //ONE VAR INSTRUCTIONS <var>
                        case '4': case '8':
                            $type_of_instr = 1; //This will tell us if the instruction have 0, 1 or more arguments
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr); //Check number of paramas of instruction first
                            type_of_params(VARIABLE_TOK, $syntax_analyzed_line, $stderr); //Then chceck type of params 

                            XML_writer($xw, 'var', $syntax_analyzed_line[1][0], 'arg1', 'type');
                            break;

                            //ONE SYMB INSTRUCTIONS <symb>
                        case '7': case '22': case '32': case '33':
                            $type_of_instr = 1;
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);
                            symb_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr); //Check if a <symb> is a const or var and nothing else

                            if ($syntax_analyzed_line[1][1] == VARIABLE_TOK) {
                                XML_writer($xw, 'var', $syntax_analyzed_line[1][0], 'arg1', 'type');
                            } else if ($syntax_analyzed_line[1][1] != LABEL_TOK) {
                                XML_writer($xw, $syntax_analyzed_line[1][0], $syntax_analyzed_line[1][2], 'arg1', 'type');
                            } else {
                                fwrite($stderr, "Instrukcia ma miesto parametru <label> i ked by ho mat nemala.\n");
                                exit(OTHER_ERR);
                            }
                            break;

                            //ONE LABEL INSTRUCTIONS <label>
                        case '5': case '28': case '29':
                            $type_of_instr = 1;
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);
                            type_of_params(LABEL_TOK, $syntax_analyzed_line, $stderr);

                            XML_writer($xw, 'label', $syntax_analyzed_line[1][0], 'arg1', 'type');
                            break;

                            //TWO ARG INSTRCTIONS <var> <symb>
                        case '0': case '19': case '24': case '27':
                            $type_of_instr = 2;
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);
                            type_of_params(VARIABLE_TOK, $syntax_analyzed_line, $stderr);
                            symb_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);

                            XML_writer($xw, 'var', $syntax_analyzed_line[1][0], 'arg1', 'type');

                            if ($syntax_analyzed_line[2][1] == VARIABLE_TOK) {
                                XML_writer($xw, 'var', $syntax_analyzed_line[2][0], 'arg2', 'type');
                            } else if ($syntax_analyzed_line[2][1] != LABEL_TOK) {
                                XML_writer($xw, $syntax_analyzed_line[2][0], $syntax_analyzed_line[2][2], 'arg2', 'type');
                            } else {
                                fwrite($stderr, "Instrukcia ma miesto parametru <label> i ked by ho mat nemala.\n");
                                exit(OTHER_ERR);
                            }
                            break;

                            //THREE ARG INSTRCTIONS <var> <symb1> <symb2>
                        case '9': case '10': case '11': case '12': case '13': case '14': case '15': case '16': case '17':
                        case '20': case '23': case '25': case '26':
                            $type_of_instr = 3;
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);
                            type_of_params(VARIABLE_TOK, $syntax_analyzed_line, $stderr);
                            symb_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);

                            XML_writer($xw, 'var', $syntax_analyzed_line[1][0], 'arg1', 'type');

                            if ($syntax_analyzed_line[2][1] == VARIABLE_TOK) {
                                XML_writer($xw, 'var', $syntax_analyzed_line[2][0], 'arg2', 'type');
                            } else if ($syntax_analyzed_line[2][1] != LABEL_TOK) {
                                XML_writer($xw, $syntax_analyzed_line[2][0], $syntax_analyzed_line[2][2], 'arg2', 'type');
                            } else {
                                fwrite($stderr, "Instrukcia ma miesto parametru <label> i ked by ho mat nemala.\n");
                                exit(OTHER_ERR);
                            }

                            if ($syntax_analyzed_line[3][1] == VARIABLE_TOK) {
                                XML_writer($xw, 'var', $syntax_analyzed_line[3][0], 'arg3', 'type');
                            } else if ($syntax_analyzed_line[3][1] != LABEL_TOK) {
                                XML_writer($xw, $syntax_analyzed_line[3][0], $syntax_analyzed_line[3][2], 'arg3', 'type');
                            } else {
                                fwrite($stderr, "Instrukcia ma miesto parametru <label> i ked by ho mat nemala.\n");
                                exit(OTHER_ERR);
                            }
                            break;

                            //NOT - <var> <symb1>
                        case '18':
                            $type_of_instr = 2;
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);
                            type_of_params(VARIABLE_TOK, $syntax_analyzed_line, $stderr);
                            symb_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);

                            XML_writer($xw, 'var', $syntax_analyzed_line[1][0], 'arg1', 'type');

                            if ($syntax_analyzed_line[2][1] == VARIABLE_TOK) {
                                XML_writer($xw, 'var', $syntax_analyzed_line[2][0], 'arg2', 'type');
                            } else if ($syntax_analyzed_line[2][1] != LABEL_TOK) {
                                XML_writer($xw, $syntax_analyzed_line[2][0], $syntax_analyzed_line[2][2], 'arg2', 'type');
                            } else {
                                fwrite($stderr, "Instrukcia ma miesto parametru <label> i ked by ho mat nemala.\n");
                                exit(OTHER_ERR);
                            }
                            break;

                            //TWO ARG INSTRCTIONS <label> <symb1> <symb2>
                        case '30': case '31':
                            $type_of_instr = 3;
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);
                            type_of_params(LABEL_TOK, $syntax_analyzed_line, $stderr);
                            symb_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);

                            XML_writer($xw, 'label', $syntax_analyzed_line[1][0], 'arg1', 'type');

                            if ($syntax_analyzed_line[2][1] == VARIABLE_TOK) {
                                XML_writer($xw, 'var', $syntax_analyzed_line[2][0], 'arg2', 'type');
                            } else if ($syntax_analyzed_line[2][1] != LABEL_TOK) {
                                XML_writer($xw, $syntax_analyzed_line[2][0], $syntax_analyzed_line[2][2], 'arg2', 'type');
                            } else {
                                fwrite($stderr, "Instrukcia ma miesto parametru <label> i ked by ho mat nemala.\n");
                                exit(OTHER_ERR);
                            }

                            if ($syntax_analyzed_line[3][1] == VARIABLE_TOK) {
                                XML_writer($xw, 'var', $syntax_analyzed_line[3][0], 'arg3', 'type');
                            } else if ($syntax_analyzed_line[3][1] != LABEL_TOK) {
                                XML_writer($xw, $syntax_analyzed_line[3][0], $syntax_analyzed_line[3][2], 'arg3', 'type');
                            } else {
                                fwrite($stderr, "Instrukcia ma miesto parametru <label> i ked by ho mat nemala.\n");
                                exit(OTHER_ERR);
                            }
                            break;

                            //Dont know about READ, so I put it here for now <var> <type>
                        case '21':
                            $type_of_instr = 2;
                            num_of_param_chceck($type_of_instr, $syntax_analyzed_line, $stderr);
                            type_of_params(VARIABLE_TOK, $syntax_analyzed_line, $stderr);

                            XML_writer($xw, 'var', $syntax_analyzed_line[1][0], 'arg1', 'type');

                            if (($syntax_analyzed_line[2][0] == 'int' || $syntax_analyzed_line[2][0] == 'string' || $syntax_analyzed_line[2][0] == 'bool') && $syntax_analyzed_line[2][1] == TYPE_TOK) {
                                XML_writer($xw, 'type', $syntax_analyzed_line[2][0], 'arg2', 'type');
                            } else {
                                fwrite($stderr, "Instrukcia READ ma spatne parametre.\n");
                                exit(OTHER_ERR);
                            }
                            break;

                        default:
                            fwrite($stderr, "Nerozpoznana instrukcia.\n");
                            exit(UNKNOWN_INVALID_OP_CODE);
                    } //switch
                } else {
                    fwrite($stderr, "Nerozpoznany token.\n");
                    exit(UNKNOWN_INVALID_OP_CODE);
                }
            } //not an EOF else
            xmlwriter_end_element($xw); // END </instruction>

        } //while
        if (count($fwjumps) != ($ipp_stats[4] + $ipp_stats[5])) //if the count of labels doesnt match with count of jumps there must be an error
        {
            $ipp_stats[6]++;
            $ipp_stats[4]--; //for correction , without it it would be greater by 1
        }

        $frequent = array_count_values($frequent); //STATS frequent param
        //array_push($ipp_stats, $frequent);

        return $ipp_stats;
    }
}
function XML_writer($xw, $text1, $text2, $start_element, $attribute)
{
    xmlwriter_start_element($xw, $start_element); // START <arg>
    xmlwriter_start_attribute($xw, $attribute);
    xmlwriter_text($xw, $text1);
    xmlwriter_end_attribute($xw);
    xmlwriter_text($xw, $text2);
    xmlwriter_end_element($xw); // END </arg>
}
function symb_param_chceck($type, $line, $stderr) // Function to check if symb is const or var
{
    $error = 0;
    if (array_key_exists(1, $line) && array_key_exists(1, $line) && array_key_exists(2, $line)) //Chceck if even second param exists
    {
        if ($line[2][1] == VARIABLE_TOK || $line[2][1] == CONST_TOK) {
            $error = 0;
        } else {
            $error = 1;
        }

        if (array_key_exists(1, $line) && array_key_exists(1, $line) && array_key_exists(2, $line) && array_key_exists(3, $line)) //Chceck if even third param exists
        {
            if ($line[3][1] == VARIABLE_TOK || $line[3][1] == CONST_TOK) {
                $error = 0;
            } else {
                $error = 1;
            }
        } else if ($type == 3) {
            $error = 1;
        }
    } else if ($type == 2) {
        $error = 1;
    } else //there is only one param
    {
        if ($line[1][1] == VARIABLE_TOK || $line[1][1] == CONST_TOK) {
            $error = 0;
        } else {
            $error = 1;
        }
    }

    if ($error) {
        fwrite($stderr, "Instrukcia ma zly typ parametru u <symb>.\n");
        exit(OTHER_ERR);
    }
}
function type_of_params($param_type, $line, $stderr) // Function to check type of params
{
    $error = 0;
    if ($line[1][1] == $param_type) {
        $error = 0;
    } else {
        $error = 1;
    }

    if ($error) {
        fwrite($stderr, "Instrukcia ma zly typ parametru.\n");
        exit(OTHER_ERR);
    }
}
function num_of_param_chceck($type, $line, $stderr) // Function to chceck number of params of instruction 
{
    $error = 0;
    if ($type == 0 && !array_key_exists(1 | 1, $line)) { //0 args
        $error = 0;
    } else if ($type == 1 && array_key_exists(1, $line) && array_key_exists(1, $line) && !array_key_exists(2, $line)) { //1 arg
        $error = 0;
    } else if ($type == 2 && array_key_exists(1, $line) && array_key_exists(1, $line) && array_key_exists(2, $line) && !array_key_exists(3, $line)) { //2 args
        $error = 0;
    } else if ($type == 3 && array_key_exists(1, $line) && array_key_exists(1, $line) && array_key_exists(2, $line) && array_key_exists(3, $line) && !array_key_exists(4, $line)) { //3 args
        $error = 0;
    } else {
        $error = 1;
    }

    if ($error) {
        fwrite($stderr, "Instrukcii chyba parameter alebo ma parameter navyse.\n");
        exit(OTHER_ERR);
    }
}

//------------------------------LEXER------------------------------
function lexer($input, $output, $stderr, $ipp_instructions)
{
    $token_data_arr = array();
    $instr_found = 0;
    $first_instruction_verific = 1; //In order to avoid for example labales named as instructions to be recognized as instructions

    global $comments;

    if ($input == NULL || $output == NULL || $stderr == NULL) {
        exit(INTERNAL_ERR);
    } else {
        while (true) {
            //EOF
            if (($line_of_code = fgets($input)) == false) {
                array_push($token_data_arr, array(EOF_TOK));
                return $token_data_arr;
            }
            //Commnets or \n check
            if ((preg_match('/^\s*$/', $line_of_code))) {
                continue;
            } else if (preg_match('/^\s*#/', $line_of_code)) { //comment on line with code e.g. DEFVAR GF@var #commentary
                $comments++; //STATS number of comments
                continue;
            }
            //Chceck if the line of code doesn t contain comment also
            $comment_in_middle = explode('#', $line_of_code); //split line by # character

            if (isset($comment_in_middle[1], $comment_in_middle)) $comments++; //STATS number of comments

            $word_split_by_regex = preg_split("/\s+/", $comment_in_middle[0]); //create array of possible words, other trash will be removed
            if (end($word_split_by_regex) == "") //if last element is "" delete it 
            {
                array_pop($word_split_by_regex);
            }
            if ($word_split_by_regex[0] == "") //if first "word" is "" then add another real word at the begining of the array 
            {
                array_shift($word_split_by_regex);
            }
            break;
        }
    }
    //Words
    foreach ($word_split_by_regex as $key => $arr_val) {
        //chceck if the word has @ in order to find out if it is a var/const or not
        if (preg_match('/@/', $arr_val)) {
            if (preg_match('/^(int|bool|string|nil)/', $arr_val)) //if word starts with one of those it is const
            {
                if (
                    preg_match('/^int@[+-]?([0-9][_]?)+$/', $arr_val) || preg_match('/^int@[+-]?0[xX][^_]([_]?[0-9a-fA-F])+$/', $arr_val) || 
                    preg_match('/^int@[+-]?0[oO]([0-7])+$/', $arr_val) || preg_match('/^bool@(true|false)/', $arr_val) ||
                    preg_match('/^nil@nil$/', $arr_val) || preg_match('/^string@$/', $arr_val) ||
                    preg_match('/^string@/', $arr_val) && preg_match('/^(string@)(\\\\\d{3,}|[^\\\\\s])*$/', $arr_val)
                ) {
                    $datatype_and_data =  explode('@', $arr_val, 2); //added limit 2, without it does not work properly e.g. WRITE string@Proměnná\032GF@counter\032obsahuje\032
                    $proccessed_word = array();
                    array_push($proccessed_word, $datatype_and_data[0]); //push datatype
                    array_push($proccessed_word, CONST_TOK);

                    //$datatype_and_data[1] = strtr($datatype_and_data[1], ["<" => "&lt;", ">" => "&gt;", "&" => "&amp;"]);

                    array_push($proccessed_word, $datatype_and_data[1]); //push data
                    array_push($token_data_arr, $proccessed_word);
                } else {
                    fwrite($stderr, "Konstanta nie je spravne zapisana, skontrolujte si vstup.\n");
                    exit(OTHER_ERR);
                }
            } else //else it is a variable 
            {
                if (preg_match('/^(GF|LF|TF)@[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$/', $arr_val)) {
                    array_push($token_data_arr, array($arr_val, VARIABLE_TOK));
                } else {
                    fwrite($stderr, "Premenna nie je spravne zapisana, skontrolujte si vstup.\n");
                    exit(OTHER_ERR);
                }
            }
        } else //it is not variable or a const
        {
            //Types
            if (preg_match('/^(int|bool|string|nil)$/', $arr_val)) {
                array_push($token_data_arr, array($arr_val, TYPE_TOK));
            } else { //Header
                if (preg_match('/[.][a-zA-Z{2}{3}]*/', $arr_val)) {
                    array_push($token_data_arr, array(HEADER_TOK));

                    if (!preg_match('/[.][a-zA-Z{2}{3}]*$/', $arr_val)) {
                        fwrite($stderr, "Hlavicka ma nespravny format.\n");
                        exit(MISSING_HEADER);
                    }
                } else //Instructions or Label
                {
                    foreach ($ipp_instructions as $index => $instr) {
                        if (strcasecmp($arr_val, $instr) == 0 && $first_instruction_verific) {
                            $instr_found = 1;
                            break;
                        }
                    }
                    if ($instr_found) {
                        array_push($token_data_arr, array($index, INSTRUCTION_TOK));
                        $instr_found = 0;
                    } else { //It has to be a label
                        if (preg_match('/^[a-zA-Z_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*$/', $arr_val)) //It is label then
                        {
                            array_push($token_data_arr, array($arr_val, LABEL_TOK));
                        } else //Or something we dont recognize
                        {
                            fwrite($stderr, "Lexem sa nepodarilo rozpoznat.\n");
                            exit(OTHER_ERR);
                        }
                    }
                };
            }
            $first_instruction_verific = 0;
        }
    } //foreach  
    return $token_data_arr;
}
