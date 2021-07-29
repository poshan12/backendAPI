<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Customlib
{
	// api ver: 3.0

    public $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('Setting_model');
    }

    public function getMonthList($month=0)
    {
        $months = array(
            0  => '',
            1  => 'january',
            2  => 'february',
            3  => 'march',
            4  => 'april',
            5  => 'may',
            6  => 'june',
            7  => 'july',
            8  => 'august',
            9  => 'september',
            10 => 'october',
            11 => 'november',
            12 => 'decmber');
     
        return $months[$month];
    }


    public function getDaysname()
    {
        $status              = array();
        $status['Monday']    = 'Monday';
        $status['Tuesday']   = 'Tuesday';
        $status['Wednesday'] = 'Wednesday';
        $status['Thursday']  = 'Thursday';
        $status['Friday']    = 'Friday';
        $status['Saturday']  = 'Saturday';
        $status['Sunday']    = 'Sunday';
        return $status;
    }
    public function getSchoolName()
    {
        $admin = $this->CI->Setting_model->getSetting();
        return $admin->name;
    }
}
