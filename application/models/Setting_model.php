<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Setting_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function get($id = null)
    {

        $this->db->select('sch_settings.id,sch_settings.lang_id,sch_settings.class_teacher,sch_settings.is_rtl,sch_settings.cron_secret_key, sch_settings.timezone,sch_settings.attendence_type,
          sch_settings.name,sch_settings.email,sch_settings.phone,languages.language,
          sch_settings.address,sch_settings.dise_code,sch_settings.date_format,sch_settings.currency,sch_settings.currency_symbol,sch_settings.start_month,sch_settings.session_id,sch_settings.fee_due_days,sch_settings.image,sch_settings.theme,sessions.session'
        );
        $this->db->from('sch_settings');
        $this->db->join('sessions', 'sessions.id = sch_settings.session_id');
        $this->db->join('languages', 'languages.id = sch_settings.lang_id');
        if ($id != null) {
            $this->db->where('sch_settings.id', $id);
        } else {
            $this->db->order_by('sch_settings.id');
        }
        $query = $this->db->get();

        if ($id != null) {
            return $query->row_array();
        } else {
            $session_array                = $this->session->has_userdata('session_array');
            $result                       = $query->result_array();
            $result[0]['current_session'] = array(
                'session_id' => $result[0]['session_id'],
                'session'    => $result[0]['session'],
            );

            if ($session_array) {
                $session_array           = $this->session->userdata('session_array');
                $result[0]['session_id'] = $session_array['session_id'];
                $result[0]['session']    = $session_array['session'];
            }

            return $result;

        }
    }

    public function getSchoolDetail($id = null)
    {

        $this->db->select('sch_settings.id,sch_settings.lang_id,sch_settings.is_rtl,sch_settings.timezone,
          sch_settings.name,sch_settings.email,sch_settings.phone,languages.language,sch_settings.attendence_type,
          sch_settings.address,sch_settings.dise_code,sch_settings.date_format,sch_settings.currency,sch_settings.currency_symbol,sch_settings.start_month,sch_settings.session_id,sch_settings.image,sch_settings.theme,sessions.session'
        );
        $this->db->from('sch_settings');
        $this->db->join('sessions', 'sessions.id = sch_settings.session_id');
        $this->db->join('languages', 'languages.id = sch_settings.lang_id');
        $this->db->order_by('sch_settings.id');
        $query = $this->db->get();
        return $query->row();
    }

    public function getSetting()
    {

        $this->db->select('sch_settings.id,sch_settings.lang_id,sch_settings.is_rtl,sch_settings.fee_due_days,sch_settings.class_teacher,sch_settings.cron_secret_key,sch_settings.timezone,
          sch_settings.name,sch_settings.email,sch_settings.phone,languages.language,
          sch_settings.address,sch_settings.dise_code,sch_settings.date_format,sch_settings.currency,sch_settings.currency_symbol,sch_settings.start_month,sch_settings.session_id,sch_settings.image,sch_settings.theme,sessions.session'
        );
        $this->db->from('sch_settings');
        $this->db->join('sessions', 'sessions.id = sch_settings.session_id');
        $this->db->join('languages', 'languages.id = sch_settings.lang_id');

        $this->db->order_by('sch_settings.id');

        $query = $this->db->get();

        return $query->row();
    }

    public function getSchoolDisplay()
    {
        $this->db->select('
          sch_settings.name,sch_settings.dise_code,sch_settings.email,sch_settings.phone,sch_settings.address,sch_settings.start_month,sch_settings.image,sessions.session');
        $this->db->from('sch_settings');
        $this->db->join('sessions', 'sessions.id = sch_settings.session_id');
        $this->db->join('languages', 'languages.id = sch_settings.lang_id');
        $this->db->order_by('sch_settings.id');
        $query = $this->db->get();
        return $query->row();
    }

    public function getCurrentSession()
    {
        $session_result = $this->get();

        return $session_result[0]['session_id'];
    }

    public function getCurrentSessionName()
    {
        $session_result = $this->get();
        return $session_result[0]['session'];
    }

    public function getCurrentSchoolName()
    {
        $session_result = $this->get();
        return $session_result[0]['name'];
    }

    public function getStartMonth()
    {
        $session_result = $this->get();
        return $session_result[0]['start_month'];
    }

    public function getCurrentSessiondata()
    {
        $session_result = $this->get();
        return $session_result[0];
    }

    public function getCurrency()
    {
        $session_result = $this->get();
        return $session_result[0]['currency'];
    }

    public function getCurrencySymbol()
    {
        $session_result = $this->get();
        return $session_result[0]['currency_symbol'];
    }

    public function getDateYmd()
    {
        return date('Y-m-d');
    }

    public function getDateDmy()
    {
        return date('d-m-Y');
    }

    public function add_cronsecretkey($data, $id)
    {

        $this->db->where("id", $id)->update("sch_settings", $data);
    }

    public function student_fields()
    {
        $fields = explode(',', 'blood_group,student_house,roll_no,category,religion,cast,mobile_no,student_email,admission_date,lastname,student_photo,student_height,student_weight,measurement_date,father_name,father_phone,father_occupation,father_pic,mother_name,mother_phone,mother_occupation,mother_pic,guardian_relation,guardian_email,guardian_pic,guardian_address,current_address,permanent_address,route_list,hostel_id,bank_account_no,national_identification_no,local_identification_no,rte');

        $this->db->select('is_blood_group as `blood_group`,is_student_house as `student_house`,roll_no,category,religion,cast,mobile_no,student_email,admission_date,lastname,student_photo,student_height,student_weight,measurement_date,father_name,father_phone,father_occupation,father_pic,mother_name,mother_phone,mother_occupation,mother_pic,guardian_relation,guardian_email,guardian_pic,guardian_address,current_address,permanent_address,route_list,hostel_id,bank_account_no,national_identification_no,local_identification_no,rte');
        $this->db->from('sch_settings');
        $this->db->order_by('sch_settings.id');
        $query  = $this->db->get();
        $result = $query->row();
        $new_object= array();
        foreach ($fields as $key => $value) {
            if ($result->{$value}) {
              $new_object[$value]=1;
            }
        }
         return $new_object;
        

    }

     public function getAdminsmalllogo(){
        $query=$this->db->select('admin_small_logo')->get('sch_settings');
         $logo=$query->row_array();
         echo $logo['admin_small_logo'];
    }

}
