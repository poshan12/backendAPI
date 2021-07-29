<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Auth_model extends CI_Model
{

    public $client_service = "t2ot";
    public $auth_key       = "123";

    public function __construct()
    {
        parent::__construct();
        // $this->load->model(array('user_model', 'setting_model', 'student_model'));
    }

    public function check_auth_client()
    {
        $client_service = $this->input->get_request_header('Client-Service', true);
        $auth_key       = $this->input->get_request_header('Auth-Key', true);
        if ($client_service == $this->client_service && $auth_key == $this->auth_key) 
        {
            return true;
        } 
        else 
        {
            return json_output(401, array('status' => 401, 'message' => 'Unauthorized.'));
        }
    }

    public function chklogin($company_email,$password)
    {
        $this->db->select('company_id, company_name ,company_gst_no ,company_email');
        $this->db->from('company');
        $this->db->where('company_email', $company_email);
        $this->db->where('company_password', $password);
        $this->db->limit(1);
        $q = $this->db->get();
        if ($q->num_rows() == 0) 
        {
            return array('status' => 401, 'message' => 'Invalid Username or Password');
        } 
        else 
        {
            $result = $q->row();
            // $result = $this->user_model->read_user_information($q->id);
            if ($result != false) 
            {
                             $session_data = array(
                                'Company id'              => $result->company_id,
                                'Company Name'      => $result->company_name,
                                'GST NO'            => $result->company_gst_no,
                                'username'        => $result->company_email,                          
                            );
                            $this->session->set_userdata('student', $session_data);
                            if ($this->db->trans_status() === false) 
                            {
                                $this->db->trans_rollback();

                                return array('status' => 500, 'message' => 'Internal server error.');
                            } 
                            else 
                            {
                                $this->db->trans_commit();
                                return array('status' => 200, 'message' => 'Successfully login.', 'id' => $q->id, 'record' => $session_data);
                            }
            }
        }
    }

    public function singup($data)
    {
        $this->db->select('company_id, company_name ,company_gst_no ,company_email');
        $this->db->from('company');
        $this->db->where('company_email', $data['company_email']);
        $this->db->limit(1);
        $q = $this->db->get();      
        if($q->num_rows() > 0) 
        {
            $q = $q->row();
            return array('status' => 401, 'message' => 'company already registerd !','company name'=>$q->company_name);
        } 
        else 
        {
            $this->db->insert('company', $data);
            $insert_id= $this->db->insert_id();
            $this->db->select('company_id, company_name ,company_gst_no ,company_email');
            $this->db->from('company');
            $this->db->where('company_id', $insert_id);
            $this->db->limit(1);
            $r = $this->db->get();     
            if($r->num_rows() > 0) 
            {
                $r = $r->row();
                return array('status' => 200, 'message' => 'Registered Successfully.','company_name'=>$r->company_name,'company_email'=>$r->company_email);
            } 
            
        }
    }

    public function logout()
    {
        $users_id = $this->input->get_request_header('User-ID', true);
        $token    = $this->input->get_request_header('Authorization', true);
        $this->session->unset_userdata('student');
        $this->session->sess_destroy();
        $this->db->where('users_id', $users_id)->where('token', $token)->delete('users_authentication');
        return array('status' => 200, 'message' => 'Successfully logout.');
    }

    public function auth()
    {
        $users_id = $this->input->get_request_header('User-ID', true);
        $token    = $this->input->get_request_header('Authorization', true);
        $q        = $this->db->select('expired_at')->from('users_authentication')->where('users_id', $users_id)->where('token', $token)->get()->row();
        if ($q == "") {
            return json_output(401, array('status' => 401, 'message' => 'Unauthorized.'));
        } else {
            if ($q->expired_at < date('Y-m-d H:i:s')) {
                return json_output(401, array('status' => 401, 'message' => 'Your session has been expired.'));
            } else {
                $updated_at = date('Y-m-d H:i:s');
                $expired_at = date("Y-m-d H:i:s", strtotime('+8760 hours'));
                $this->db->where('users_id', $users_id)->where('token', $token)->update('users_authentication', array('expired_at' => $expired_at, 'updated_at' => $updated_at));
                return array('status' => 200, 'message' => 'Authorized.');
            }
        }
    }

}
