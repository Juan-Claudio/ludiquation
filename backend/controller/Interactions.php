<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'controller/CheckFormat.php';
require_once 'model/Server.php';


class Interactions
{
	static $resolved_equations_nb = [];
	
	public function __construct()
	{
		throw new Error('Interactions is not instanciable class.');
	}

    static function show($color, $str, $nextline=true)
    {
        echo("<span style=\"color:$color\">");
        print_r($str);
        if($nextline){ echo("</span><br>"); }
        else{ echo("</span>"); }
    }

	static function reverseEquation($blockId)
    {
        $eqId = CheckFormat::blockId_toIntArr($blockId)[0];
        $eqWork = Server::getIn_CurrGrp_equation($eqId);
        $left_member = [];
        $right_member = [];
        $left_side = true;
        foreach($eqWork as $content)
        {
            if($content === '='){ $left_side=false; }
            else if($left_side){ array_push($left_member,$content); }
            else{ array_push($right_member,$content); }
        }
        $eqWork = array_merge($right_member,['='],$left_member);
        Server::setIn_CurrGrp_equation($eqId, $eqWork);
        CheckFormat::equation_resolved($eqId);
    }
	
	static function unOrselect_block($block_cliqued)
    {          
        //1)Bloc cliqued is already selected ?
        $isUnselection = Server::unselect_block($block_cliqued);
        if($isUnselection){ return true; }
        
        //2)Are 2 blocs already selected ?
        $twoBlocSelected = Server::get_selected_blocks_nb()===2;
        if($twoBlocSelected){ return false; }

        //3)Is one bloc selected in same member of bloc cliqued ?
        $oneBlocSelected = Server::get_selected_blocks_nb()===1;
        if($oneBlocSelected)
        {
            $blocksInSameMember = CheckFormat::isBlocksInSameMember(
                $block_cliqued, Server::get_selected_block(0));
            if(!$blocksInSameMember){ return false; }
        }

        //4)Select bloc cliqued.
        Server::select_block($block_cliqued); return true;
    }
	static function unselectAll(){ Server::unselect_all(); }

	static function replaceSelectionByNewblock($newblockContent)
    {
        $selectedblocksNb = Server::get_selected_blocks_nb();
        $block1 = Server::get_selected_block(0);
        $block1Id_arr = CheckFormat::blockId_toIntArr($block1);

        //1)is at least one block selected ?
        if($selectedblocksNb===0)
        {
            return 'err:noblockSelected';
        }

        //2)is newblockContent valid ?
        $contentValid = CheckFormat::isContentValid($newblockContent);
        if(preg_match('/^err/',$contentValid)){ return $contentValid;}

        Server::move($block1Id_arr[0]);

        //3)calculate member before replace by new block
        $equalId = CheckFormat::equalblockId_Of($block1, true);
        $isLeftMember = ($block1Id_arr[1]<$equalId) ? true : false;
        $equation = Server::getIn_CurrGrp_equation($block1Id_arr[0]);
        $member = '';
        $member_value_before = 0;

        $isInWorkingMember = function($index) use($isLeftMember,$equalId)
        {
            if($isLeftMember){ return $index<$equalId; }
            return $index>$equalId;
        };

        //member before replace by new block
        $member = CheckFormat::replace_unknowns( join('', array_map(
				function($val)
				{
					$index = array_search($val,$equation);
					if($isInWorkingMember($index)){ return $val; }
					else{ return ''; }
				},
				$equation
			)
		));
        $member_value_before = CheckFormat::calculate($member); //float

        //4)calculate member with new block
        $block2 = ($selectedblocksNb===2) ? Server::get_selected_block(1) : false;
        $block2Id_arr = ($selectedblocksNb===2) ? CheckFormat::blockId_toIntArr($block2) : false;
        $member_value_after = 0;
        $workingEquation = $equation;
        $isNewblockValid = false;

        //member after replace by new block
        $workingEquation[$block1Id_arr[1]]=$contentValid;
        if($selectedblocksNb===2){ $workingEquation[$block2Id_arr[1]]=''; }
        
        $member = CheckFormat::replace_unknowns( join('', array_map(
            function($val)
			{
				$index=array_search($val,$workingEquation);
                if($isInWorkingMember($index)){ return $val; }
                else{ return ''; }
            },
			$workingEquation
			)
        ));
        $member_value_after = CheckFormat::calculate($member); //float

        $isNewblockValid = $member_value_before===$member_value_after;

        if($isNewblockValid)
        {
            Server::setIn_CurrGrp_equation(
                $block1Id_arr[0],
                array_filter($workingEquation, function($val){ return $val!==''; })
            );
            CheckFormat::score($block1Id_arr[0]);
            return 'correct';
        }
        else{ return 'err:noEqualblock'; }
    }

	//TO TRANSLATE FROM JS TO PHP
    static function addDoubleblock($newblockContent)
    {
        //1)is at least one block selected ?
        $selectedblocksNb = Server::get_selected_blocks_nb();
        if($selectedblocksNb===0)
        {
            return 'err:noblockSelectedEq';
        }

        //2)is newblockContent valid ?
        $contentValid = CheckFormat::isContentValid($newblockContent, true);
        if(preg_match('/^err/',$contentValid)){ return $contentValid;}

        //3)is too much blocks in ludiwindow ?
        if(Server::get_all_blocks_nb()>40){ return 'err:tooMuchblocks'; }

        //4)add blocks
        $eqId = CheckFormat::blockId_toIntArr(Server::get_selected_block(0))[0];
        $equation = Server::getIn_CurrGrp_equation($eqId);
        $equalId = array_search('=',$equation);
        switch($contentValid[0])
        {
            case '×': 
            case '/':
                $equation[0] = '('.$equation[0];
                $equation[$equalId-1] = $equation[$equalId-1].')';
                $equation[$equalId+1] = '('.$equation[$equalId+1];
                $equation[count($equation)-1] = $equation[count($equation)-1].')';
                break;
            case '+': break;
            case '-': break;
        }
        
        array_splice($equation, $equalId, 0, $contentValid);
        array_push($equation, $contentValid);
        Server::setIn_currGrp_equation($eqId,$equation);
        return 'correct';
    }

    static function remove2blocks()
    {   
        //1)Is two blocks selected ?
        if(Server::get_selected_blocks_nb()!==2)
        { return 'err:twoSelectedblocksNeeded'; }

        $block1_id = CheckFormat::blockId_toIntArr( Server::get_selected_block(0) );
        $block2_id = CheckFormat::blockId_toIntArr( Server::get_selected_block(1) );
        Server::move($block1_id[0]);


        //2)calculate member before cancel out
        //TODO ADD two blocks equal content ([-+] exclude) ??
        $equation = Server::getIn_CurrGrp_equation($block1_id[0]);
        $equalId = array_search('=',$equation);
        $workingEquation = $equation;
        $member = '';
        $member_value_before = 0;
        $member_value_after = 0;
        $isLeftMember = $block1_id[1]<$equalId;

        //calulate member value before cancel out
        $member = CheckFormat::replace_unknowns( join('', array_filter(
            $workingEquation,
            function($val, $index) use($isLeftMember, $equalId)
            {
                return ($isLeftMember) ? $index<$equalId : $index>$equalId;
            },
            ARRAY_FILTER_USE_BOTH
        )));
        $member_value_before = CheckFormat::calculate($member); //float
        
        //3)calculate member after cancel out
        $member = CheckFormat::replace_unknowns( join('', array_filter(
            $workingEquation,
            function($val, $id) use($block1_id, $block2_id, $isLeftMember, $equalId)
            {
                if($id!==$block1_id[1] && $id!==$block2_id[1])
                {
                    return ($isLeftMember) ? $id<$equalId : $id>$equalId;
                }
                return false;
            },
            ARRAY_FILTER_USE_BOTH
        )));
        $member_value_after = CheckFormat::calculate($member); //float
        
        //4)Is only 2 block in the member ?
        $member_blocks_nb = ($isLeftMember) ? $equalId : $count($equation)-($equalId+1) ;
        if($member_blocks_nb===2){ return 'err:Only2blocks'; }
        
        if($member_value_before===$member_value_after)
        {
            Server::setIn_CurrGrp_equation(
                $block1_id[0],
                array_filter($workingEquation,
                    function($val, $id) use($block1_id, $block2_id)
                    {
                        return ($id!==$block1_id[1] && $id!==$block2_id[1]);
                    },
                    ARRAY_FILTER_USE_BOTH
                )
            );
            CheckFormat::score($block1_id[0]);
            return 'correct';
        }
        return 'err:notCancelOut';
    }

    static function combine2blocks()
    {
        //1)Is two blocks selected ?
        if(Server::get_selected_blocks_nb()!==2)
        { return 'err:twoSelectedblocksNeeded'; }

        //2)Is to blocks stuck ?
        $block1_id = CheckFormat::blockId_toIntArr( Server::get_selected_block(0) );
        $block2_id = CheckFormat::blockId_toIntArr( Server::get_selected_block(1) );
        if(abs($block1_id[1]-$block2_id[1])!==1){ return 'err:twoblocksNotStuck'; }

        //3)Is combined block content <= 7 characters ?
        $equation = Server::getIn_CurrGrp_equation( $block1_id[0] );
        $combinedblocks_content = $equation[$block1_id[1]] . $equation[$block2_id[1]];
        if(strlen($combinedblocks_content)>7){ return 'err:combinedLength'; }

        //4)Combined in same order
        $receiverblockId = ($block1_id[1]-$block2_id[1]<0) ? $block1_id[1] : $block2_id[1];
        $combinedblocks_content = ($block1_id[1]-$block2_id[1]<0)
            ? $combinedblocks_content
            : $equation[$block2_id[1]] . $equation[$block1_id[1]];
        
        $equation[$receiverblockId] = $combinedblocks_content;
        array_splice($equation, $receiverblockId+1, 1);
        self::show('#099',$equation);
        Server::setIn_CurrGrp_equation($block1_id[0], $equation);
        CheckFormat::equation_resolved( $block1_id[0] );
        return 'correct';
    }
}