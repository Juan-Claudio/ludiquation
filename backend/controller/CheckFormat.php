<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once('model/Server.php');

class CheckFormat
{
    public function __construct()
	{
		throw new Error('CheckFormat is not instanciable class.');
	}

    static function blockId_toIntArr($str)//:[int,int]
    {
        if($str===''){ return false; }
        return (
            array_map(
                function($value){ return intval($value); },
                explode(
                    'b',
                    preg_replace('/eq|z/','',$str)
                )
            )
        );
    }

    static function equalBlockId_Of($blockId, $intType=false)
    {
        $eqId = self::blockId_toIntArr($blockId)[0];
        $equalId = array_search('=', Server::getIn_CurrGrp_equation($eqId));
        return ($intType) ? $equalId : "eq{$eqId}b{$equalId}";
    }

    static function isBlocksInSameMember($blockId1, $blockId2)
    {
        $blockId1_arr = self::blockId_toIntArr($blockId1);
        $blockId2_arr = self::blockId_toIntArr($blockId2);
        
        //1)Are blocs in the same equation ?
        if($blockId1_arr[0]!==$blockId2_arr[0]){ return false; }

        //2)Are blocs in same member ?
        $EqualIndex = self::equalBlockId_Of($blockId1, true);
        $bloc1InFirstMember = $blockId1_arr[1]<$EqualIndex;
        $bloc2InFirstMember = $blockId2_arr[1]<$EqualIndex;
        if($bloc1InFirstMember^$bloc2InFirstMember){ return false; }

        //3)Blocs are in the same member of the equation
        return true;
    }

    static function isContentValid($content, $doubleBloc=false)
    {
        //preformat content string
        $content = self::simplySign(
            strtolower(preg_replace('/\s/','',$content))
        );
                    
        if($content===''){ return 'err:empty'; }
        if(strlen($content)>7){ return 'err:length'; }

        //check if not contains invalid char
        if(preg_match('/[^a-z0-9\-\+\/\*×÷:\(\),\.]/u',$content)){ return 'err:invalid'; }
        
        //check if parenthesis correctly opened and closed
        $parentOpNb = substr_count($content,'(');
        $parentClNb = substr_count($content,')');
        if($parentOpNb!==$parentClNb){ return 'err:parent'; }
        if(strrpos($content,'(') > strrpos($content,')'))
        { return 'err:parent'; }

        //check first and last char according to type of new bloc
        $firstChar_pattern = '/^[,\.\/\*×÷:\)]/u';
        if($doubleBloc){ $firstChar_pattern = '/^[^\+\-\*\/×÷:]/u'; }
        if(preg_match($firstChar_pattern,$content)){ return 'err:firstChar'; }
        if(preg_match('/[^\)0-9a-z]$/',$content)){ return 'err:lastChar'; }
        
        //check succession of chars
        if(preg_match('/[^0-9][,\.]/',$content)){ return 'err:dot'; }
        if(preg_match('/[,\.][^0-9]/',$content)){ return 'err:dot'; }
        if(preg_match('/[,\.][0-9]+[,\.]/',$content)){ return 'err:dot'; }
        if(preg_match('/[\+\-][^a-z0-9\(]/',$content)){ return 'err:sign'; }
        if(preg_match('/[\/:÷\*×][^0-9a-z\(\+\-]/u',$content)){ return 'err:op'; }
        if(preg_match('/[\(][^a-z0-9\+\-\(]/',$content)){ return 'err:parentOp'; }
        
        return str_replace(
            [',','*','÷',':'],
            ['.','×','/','/'],
            $content
        );
    }

    static function score($eqId)
    {
        if(Server::getIn_currMoves_value($eqId)>=0)
        {
            if(!self::equation_resolved($eqId))
            { Server::add100_toScore(); }
        }
    }

    static function equation_resolved($eqId)
    {
        $equation = Server::getIn_CurrGrp_equation($eqId);
        $unknown = str_replace('+','',$equation[0]);
        $nb_resolved_eq = 0;

        if(count($equation)>3){ return false; }
        if(!preg_match('/^\+*[a-z]$/',equation[0])){ return false; }
        if($equation[2] !== Server::getIn_currUnknowns_value($unknown)){ return false; }        
        Server::add500_toScore();
        Server::addBonus_toScore($eqId);
        self::$resolved_equations_nb[$eqId]=1;
        
        $nb_resolved_eq = array_reduce(
            function($carry, $val){ $carry += $val; return $carry; },
            self::$resolved_equations_nb
        );
        
        if($nb_resolved_eq === count(Server::getIn_CurrGrp_allEquations()))
        { Server::level_up(); }
        return true;
    }

    static function replace_unknowns($member_str)
    {
        $unknowns = Server::getIn_currGroup_unkowns();
        $regExp = '/\d*[';

        //add × before or after parenthesis
        $member_str = preg_replace_callback(
            '/\w\(|\)\w/',
            function($val){ return $val[0][0].'×'.$val[0][1]; },
            $member_str
        );

        foreach($unknowns as $key=>$value){ $regExp .= $key; }
        $regExp .= ']\d*/';

        return preg_replace_callback(
            $regExp,
            function($found) use($unknowns){
                $pre= $x = $post='';
                $op1 = $op2 = '×';
                $firstIsDigit = preg_match('/\d/',$found[0][0]);
                
                switch(strlen($found[0]))
                {
                    case 1: $x=$found[0]; $op1=$op2=''; break;
                    case 2:
                        $pre = ($firstIsDigit) ? $found[0][0] : '';
                        $post = ($firstIsDigit) ? '' : $found[0][1];
                        $x = ($firstIsDigit) ? $found[0][1] : $found[0][0];
                        if($firstIsDigit){ $op2=''; }
                        else{ $op1 = ''; }
                        break;
                    case 3:
                        $pre = $found[0][0];
                        $x = $found[0][1];
                        $post = $found[0][2];
                        break;
                    default: throw new Error('length of $found[0](= '.$found[0].' )>3');
                }
                return $pre.$op1.$unknowns[$x].$op2.$post;
            },
            $member_str
        );
    }

    static function simplySign($str)
    {
        $str = preg_replace('/\)\(/', ')×(', $str);
        return preg_replace_callback(
            '/[-+]{2,}/',
            function($found){
                if(substr_count($found,'-')%2 === 0)
                { return '+'; }
                else{ return '-'; }
            },
            $str
        );
    }

    static function multiply_divide($str)
    {        
        $secu = 1;
        while(preg_match('/[\/×]/',$str) && $secu<=20)
        {
            $str = preg_replace_callback(
                '/[-+]*\d+(\.\d+)*[×\/][-+]*\d+(\.\d+)*/u',
                function($found){
                    $isProduct = !str_contains($found[0], '/');
                    $curr_opSign = ($isProduct) ? '×' : '/';
                    $curr_op = explode($curr_opSign, $found[0]);
                    $curr_op[0] = floatval($curr_op[0]);
                    $curr_op[1] = floatval($curr_op[1]);
                    $resPro = $curr_op[0]*$curr_op[1];
                    $resDiv = $curr_op[0]/$curr_op[1];
                    if($isProduct){ return ($resPro>0) ? '+'.$resPro : $resPro.''; }
                    else{ return ($resDiv>0) ? '+'.$resDiv : $resDiv.''; }
                },
                $str
            );
            if($secu===20){ throw new Error('multiply_divide($str): too much loop!'); }
            $secu++;
        }
        return $str;
    }

    static function add_sub($str)
    {
        $result = 0;
        if(!preg_match('/[-+]/',$str[0])){ $str = '+'.$str; }
        preg_match_all('/[-+]\d+(\.\d+)*/', $str, $allMatch);
        return array_reduce(
            $allMatch[0],
            function($carry, $currVal)
            {
                $carry += floatval($currVal);
                return $carry;
            }
        );
    }

    static function calculate_portion($str)//:float
    {
        //1) simplify signs
        $str = self::simplySign($str);
        //2) multiplication/division
        $str = self::multiply_divide($str);
        //3) add/sub 4) return result
        return self::add_sub($str);
    }

    static function calculate($str)
    {
        if($str===''){ return 0.0; }

        //1) simplify Sign of entire expression
        $str = self::simplySign($str);

        //2)replace parenthesis portion by value
        $secu = 1;
        while(preg_match('/\(/',$str) && $secu<=20)
        {
            $str = preg_replace_callback(
                '/\([\d\-+\/×\.]+\)/u',
                function($found){
                    return self::calculate_portion(
                        substr($found[0],1,strlen($found[0])-2)
                    );
                },
                $str
            );
            if($secu===20){ throw new Error('calculate($str='.$str.') too much loop!'); }
            $secu++;
        }

        //3) calculate the remaining expression
        return self::calculate_portion($str);
    }

    static function trad($mess)
    {
        $traduc = [
            'empty'=>'Psst! You forgot to introduce value.',
            'length'=>'Ay! To more characters in your block. Max. 7.',
            'invalid'=>'Outch! Forbidden characters inserted.',
            'parent'=>'Oups! Some trouble with your parenthesis.',
            'firstChar'=>'Oh! Your first character can\'t be here.',
            'lastChar'=>'Ey! Your new block content ends badly.',
            'dot'=>'Almost one dot is lost no?',
            'sign'=>'Almost one sign \'+\' or \'-\' is lost no?',
            'op'=>'Almost one operation sign has escaped you!',
            'parentOp'=>'Hep! Operation sign lost near a parenthesis.',
            'noblockSelected'=>'Hmm.. You didn\'t select any block...',
            'noEqualblock'=>'This block is not equal to the selection.',
            'noblockSelectedEq'=>'I need almost one selected block to know what equation.',
            'tooMuchblocks'=>'Too much blocks in the game. Combine blocks to do some space.',
            'notCancelOut'=>'Arf! These two blocks do not cancel each other out.',
            'combinedLength'=>'Oh Oh! Combined block content to long. Max. 7.',
            'twoSelectedblocksNeeded'=>'Euh... Not enough blocks selected. 2 needed.',
            'Only2blocks'=>'Only two blocks, you must to convert them manually.',
            'twoblocksNotStuck'=>'You can combine only two stuck blocks.'
        ];
        if(!preg_match('/^err:/',$mess))
        {
            return 'Correct!';
        }
        else
        {
            return $traduc[str_replace('err:','',$mess)] ?? $mess;
        }
    }
}