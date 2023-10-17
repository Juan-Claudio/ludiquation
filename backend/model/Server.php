<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class Server
{
    public function __construct()
	{
		throw new Error('Server is not instanciable class.');
	}

    static private $selected_blocks = [];
    static private $score_level = ['score'=>0, 'level'=>0];
    static private $equations_groups_data = [];
    static private $initiated = false;

    static function init_once()
    {
        if(self::$initiated===true){ return false; }
        self::$equations_groups_data = json_decode(
            file_get_contents('model/bd.json'),
            true
        );
        self::$initiated = true;
        return true;
    }
    
    //SHOW
    static function show_data()
    {
        foreach(self::get_equationsGroups_data() as $key => $value)
        {
            echo "<b>Index nº$key</b><br>";
            foreach($value as $id => $val)
            {
                echo "- - - - $id: <br>";
                foreach($val as $i => $v)
                {
                    if($id==='equations')
                    {
                        echo ". · . · . · . · equation $i -> ";
                        foreach($v as $x => $elmt)
                        {
                            echo "[ $elmt ]";
                        }
                        echo '<br>';
                    }
                    else
                    {
                        echo "· · · · · · · $i -> ";
                        print_r($v);echo "<br>";
                    }
                }
            }
        }
    }

    //GETTERs
    static function get_score_level(){ return self::$score_level; }
    static function get_score(){ return self::get_score_level()['score']; }
    static function get_level(){ return self::get_score_level()['level']; }
    //get groups
    static function get_equationsGroups_data()
    {
        self::init_once();
        return self::$equations_groups_data;
    }
    static function get_currGrp_data()
    {
        return self::get_equationsGroups_data()[self::$score_level['level']];
    }
    //get equations
    static function getIn_CurrGrp_equation($id)
    {
        return self::get_currGrp_data()['equations'][$id];
    }
    static function getIn_CurrGrp_allEquations()
    {
        return self::get_currGrp_data()['equations'];
    }
    //get bloks
    static function get_all_blocks_nb()
    {
        $eqArr = self::getIn_CurrGrp_allEquations();
        return count($eqArr, COUNT_RECURSIVE) - count($eqArr);
    }
    static function get_selected_blocks(){ return self::$selected_blocks; }
    static function get_selected_blocks_nb(){ return count(self::$selected_blocks); }
    static function get_selected_block($id){ return self::$selected_blocks[$id] ?? ''; }
    //get unknowns
    static function getIn_currGroup_unkowns()
    {
        return self::get_currGrp_data()['unknowns'];
    }
    static function getIn_currUnknowns_value($letter)
    {
        return self::get_currGrp_data()['unknowns'][$letter];
    }
    //get moves
    static function getIn_currMoves_value($eqId)
    {
        return self::get_currGrp_data()['moves'][$eqId];
    }

    //SETTERS
    static private function add_toScore($points){ self::$score_level['score']+=$points; }
    static function add100_toScore(){ self::add_toScore(100); }
    static function add500_toScore(){ self::add_toScore(500); }
    static function addBonus_toScore($eqId)
    { self::add_toScore(100*self::getIn_currMoves_value($eqId)); }

    static function level_up()
    {
        if(self::get_level() < count(self::get_equationsGroups_data()))
        {
            self::$score_level['level']++;
            return 'level_up';
        }
        else{ return 'game_over'; }
    }

    static function select_block($block)
    {
        if(count(self::$selected_blocks)>1){ return false; }
        array_push(self::$selected_blocks,$block);
        return true;
    }
    static function unselect_block($block)
    {
        if(array_search($block, self::$selected_blocks)!==false)
        {
            if(self::$selected_blocks[0]===$block){ array_shift(self::$selected_blocks); }
            else{ array_pop(self::$selected_blocks); }
            return true;
        }
        return false;
    }
    static function unselect_all(){ self::$selected_blocks = []; }

    //set equation
    /**
     * {int} $id
     * {array} $newEq
     */
    static function setIn_CurrGrp_equation($id, $newEq)
    {
        self::$equations_groups_data[self::$score_level['level']]['equations'][$id] = $newEq;
    }

    static function move($eqId)
    {
        if(self::getIn_currMoves_value($eqId)>=0)
        { self::get_currGrp_data()['moves'][$eqId]--; }
    }
}
