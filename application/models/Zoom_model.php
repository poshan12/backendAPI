<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Zoom_model extends CI_Model 
{
   public function __construct() 
   {
        parent::__construct();
        $CI =& get_instance();
        $CI->load->model('setting_model');
        $this->current_session = $this->setting_model->getCurrentSession();
    }

    // -----------------------------------------
	public function updateConferenceDetails($details)
	{
		$this->db->trans_start();
        $this->db->trans_strict(false);
		$this->db->where('conference_id', $details['conference_id']);
		$this->db->where('student_id', $details['student_id']);
        $q = $this->db->get('conferences_history');
        if($q->num_rows() > 0) 
        {
            $row = $q->row();
            $logindatetime =  $details['login_datetime'];
			$logoutdatetime =  $details['logout_datetime'];
            $data_insert['login_time'] = $logindatetime;
            $data_insert['logout_time'] = $logoutdatetime;
            $this->db->where('id',$row->id);
			$this->db->update('conferences_history', $data_insert);     
            $data['recordNo']=$row->id;      
			if($this->db->affected_rows()>0)
			{
                // $data['recordNo']=$row->id;
                $data['success'] = 1;
                $data['Msg']='Update Successfully';
                $data['conference_data'] = $q->result();
                $this->db->trans_complete(); 
                return $data;
               
			}
			else 
			{
                // $data['recordNo']=$row->id;
                $data['status'] = 401;
                $data['success'] = 0;
                $data['Msg']='Record Not Updated';
                return $data;
			}
            
        } 
        else 
        {
            $data['success'] = 0;
			$data['errorMsg'] = "Record Not Found";
			return $data;
        }

        // $this->db->trans_complete();
        // if ($this->db->trans_status() === false) 
        // {
        //     $this->db->trans_rollback();
        //     return FALSE;
        // }
        // else 
        // {
        //     return TRUE;
        // }
	}
	// -----------------------------------------
   
}
?>