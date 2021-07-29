<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Emailconfig_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function getActiveEmail()
    {
        // $sql = "SELECT * FROM `email_config`";
        // $query = $this->db->query($sql);
        // if($query->num_rows() == 1) 
        // {
        //     return $query->result();
        // } 
        // else 
        // {
        //     return false;
        // }

        $this->db->select()->from('email_config');
        $this->db->where('is_active', 'yes');
        $query = $this->db->get();
        return $query->row();
    }

}
