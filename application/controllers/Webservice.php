<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Webservice extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->library('mailer');
        $this->load->library(array('customlib', 'enc_lib'));
        $this->load->model(array('auth_model', 'route_model', 'student_model', 'setting_model', 'attendencetype_model', 'studentfeemaster_model', 'feediscount_model', 'teachersubject_model', 'timetable_model', 'user_model', 'examgroup_model', 'webservice_model', 'grade_model', 'librarymember_model', 'bookissue_model', 'homework_model', 'event_model', 'vehroute_model', 'timeline_model', 'module_model', 'paymentsetting_model', 'customfield_model', 'subjecttimetable_model', 'onlineexam_model', 'leave_model', 'chatuser_model', 'conference_model', 'syllabus_model'));
    }

    public function logout()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            // $check_auth_client = $this->auth_model->check_auth_client();
            // if ($check_auth_client == true) {
            $response = $this->auth_model->logout();
            json_output($response['status'], $response);
            // }
        }
    }

    public function forgot_password()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {

            $_POST = json_decode(file_get_contents("php://input"), true);
            $this->form_validation->set_data($_POST);
            $this->form_validation->set_rules('email', 'Email', 'trim|required');
            $this->form_validation->set_rules('usertype', 'User Type', 'trim|required');
            if ($this->form_validation->run() == false) {
                $errors = validation_errors();
            }

            if (isset($errors)) {
                $respStatus = 400;
                $resp       = array('status' => 400, 'message' => $errors);
            } else {
                $email    = $this->input->post('email');
                $usertype = $this->input->post('usertype');
                $site_url = $this->input->post('site_url');

                $result = $this->user_model->forgotPassword($usertype, $email);

                if ($result && $result->email != "") {

                    $verification_code = $this->enc_lib->encrypt(uniqid(mt_rand()));
                    $update_record     = array('id' => $result->user_tbl_id, 'verification_code' => $verification_code);
                    $this->user_model->updateVerCode($update_record);
                    if ($usertype == "student") {
                        $name = $result->firstname . " " . $result->lastname;
                    } else {
                        $name = $result->guardian_email;
                    }
                    $resetPassLink = $site_url . '/user/resetpassword' . '/' . $usertype . "/" . $verification_code;

                    $body       = $this->forgotPasswordBody($name, $resetPassLink);
                    $body_array = json_decode($body);

                    if (!empty($this->mail_config)) {

                        $result = $this->mailer->send_mail($email, $body_array->subject, $body_array->body);
                        if ($result) {
                            $respStatus = 200;
                            $resp       = array('status' => 200, 'message' => "Please check your email to recover your password");
                        } else {
                            $respStatus = 200;
                            $resp       = array('status' => 200, 'message' => "Sending of message failed, Please contact to Admin.");
                        }
                    }

                } else {
                    $respStatus = 401;
                    $resp       = array('status' => 401, 'message' => "Invalid Email or User Type");

                }
            }
            json_output($respStatus, $resp);

        }

    }

    public function forgotPasswordBody($name, $resetPassLink)
    {
        //===============
        $subject = "Password Update Request";
        $body    = 'Dear ' . $name . ',
                <br/>Recently a request was submitted to reset password for your account. If you didn\'t make the request, just ignore this email. Otherwise you can reset your password using this link <a href="' . $resetPassLink . '"><button>Click here to reset your password</button></a>';
        $body .= '<br/><hr/>if you\'re having trouble clicking the password reset button, copy and paste the URL below into your web browser';
        $body .= '<br/>' . $resetPassLink;
        $body .= '<br/><br/>Regards,
                <br/>' . $this->customlib->getSchoolName();

        //======================
        return json_encode(array('subject' => $subject, 'body' => $body));
    }


    public function dashboard()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $date_list             = array();
                    $params                = json_decode(file_get_contents('php://input'), true);
                    $student_id            = $params['student_id'];
                    $date_from             = $params['date_from'];
                    $date_to               = $params['date_to'];
                    $student               = $this->student_model->get($student_id);
                    $student_login         = $this->user_model->getUserLoginDetails($student_id);
                    $attendence_percentage = 0;
                    $resp                  = array();
                        // print_r($student);
                    $student_session_id = $student->student_session_id;
                    $student_attendence = $this->attendencetype_model->getAttendencePercentage($date_from, $date_to, $student_session_id);
                    $student_homework   = $this->homework_model->getStudentHomeworkPercentage($student_session_id, $student->class_id, $student->section_id);
                    if ($student_attendence->present_attendance > 0 && $student_attendence->total_count > 0) {

                        $attendence_percentage = $student_attendence->present_attendance / $student_attendence->total_count * 100;
                    }

                    $school_setting = $this->setting_model->getSchoolDetail();
                    $resp['attendence_type'] = $school_setting->attendence_type;
                    $resp['class_id']                      = $student->class_id;
                    $resp['section_id']                    = $student->section_id;
                    $resp['student_attendence_percentage'] = round($attendence_percentage);
                    $resp['student_homework_incomplete']   = round($student_homework->total_homework - $student_homework->completed);
                    $resp['student_incomplete_task']       = $this->event_model->incompleteStudentTaskCounter($student_login['id']);
                    // $resp['public_events'] = $this->event_model->getPublicEvents($student_login['id']);
                    $resp['public_events'] = $this->event_model->getPublicEvents($student_login['id'], $date_from, $date_to);

                    foreach ($resp['public_events'] as &$ev_tsk_value) {
                        $evt_array = array();
                        if ($ev_tsk_value->event_type == "public") {
                            $start = strtotime($ev_tsk_value->start_date);
                            $end   = strtotime($ev_tsk_value->end_date);

                            for ($st = $start; $st <= $end; $st += 86400) {
                                if ($st >= strtotime($date_from) && $st <= strtotime($date_to)) {

                                    $date_list[date('Y-m-d', $st)] = date('Y-m-d', $st);
                                    $evt_array[]                   = date('Y-m-d', $st);
                                }
                            }

                            $ev_tsk_value->events_lists = implode(",", $evt_array);
                        } elseif ($ev_tsk_value->event_type == "task") {

                            $date_list[date('Y-m-d', strtotime($ev_tsk_value->start_date))] = date('Y-m-d', strtotime($ev_tsk_value->start_date));
                            $evt_array[]                                                    = date('Y-m-d', strtotime($ev_tsk_value->start_date));
                            $ev_tsk_value->events_lists                                     = implode(",", $evt_array);

                        }
                    }
                    $resp['date_lists'] = implode(",", $date_list);

                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getApplyLeave()
    {
        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $data       = array();
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $student_id = $params['student_id'];
                    $student    = $this->student_model->get($student_id);

                    $result               = $this->leave_model->get($student->student_session_id);
                    $data['result_array'] = $result;

                    json_output($response['status'], $data);
                    // json_output($response['status'], $response);
                }
            }

        }
    }

    public function addLeave()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $data = $this->input->POST();

                    $this->form_validation->set_data($data);
                    $this->form_validation->set_error_delimiters('', '');
                    $this->form_validation->set_rules('from_date', 'From', 'required|trim');
                    $this->form_validation->set_rules('to_date', 'To', 'required|trim');
                    $this->form_validation->set_rules('apply_date', 'Apply Date', 'required|trim');
                    $this->form_validation->set_rules('student_id', 'Student ID', 'required|trim');
                    // $this->form_validation->set_rules('base_url', 'base_url', 'required|trim');

                    if ($this->form_validation->run() == false) {

                        $sss = array(
                            'from_date'  => form_error('from_date'),
                            'to_date'    => form_error('to_date'),
                            'apply_date' => form_error('apply_date'),
                            'student_id' => form_error('student_id'),
                            'base_url'   => form_error('base_url'),
                        );
                        $array = array('status' => '0', 'error' => $sss);
                        // echo json_encode($array);

                    } else {
                        //==================
                        $student = $this->student_model->get($this->input->post('student_id'));

                        $data = array(
                            'from_date'          => $this->input->post('from_date'),
                            'to_date'            => $this->input->post('to_date'),
                            'apply_date'         => $this->input->post('apply_date'),
                            'reason'             => $this->input->post('reason'),
                            'student_session_id' => $student->student_session_id,
                        );
                        // $base_url = $this->input->post('base_url');

                        $leave_id    = $this->leave_model->add($data);
                        $upload_path = $this->config->item('upload_path') . "/student_leavedocuments/";

                        if (isset($_FILES["file"]) && !empty($_FILES['file']['name'])) {
                            $fileInfo = pathinfo($_FILES["file"]["name"]);
                            $img_name = $leave_id . '.' . $fileInfo['extension'];
                            move_uploaded_file($_FILES["file"]["tmp_name"], $upload_path . $img_name);
                            $data = array('id' => $leave_id, 'docs' => $img_name);
                            $this->leave_model->add($data);
                        }

                        $array = array('status' => '1', 'msg' => 'Success');
                    }
                    json_output(200, $array);
                }
            }

        }
    }

     public function updateLeave()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {

                    $data = $this->input->POST();
                    $this->form_validation->set_data($data);
                    $this->form_validation->set_error_delimiters('', '');
                    $this->form_validation->set_rules('id', 'From', 'required|trim');
                    $this->form_validation->set_rules('from_date', 'From', 'required|trim');
                    $this->form_validation->set_rules('to_date', 'To', 'required|trim');
                    $this->form_validation->set_rules('apply_date', 'Apply Date', 'required|trim');

                    if ($this->form_validation->run() == false) {

                        $sss = array(
                            'id'         => form_error('id'),
                            'from_date'  => form_error('from_date'),
                            'to_date'    => form_error('to_date'),
                            'apply_date' => form_error('apply_date'),

                        );
                        $array = array('status' => '0', 'error' => $sss);
                        // echo json_encode($array);

                    } else {
                        //==================
                        $leave_id = $this->input->post('id');
                        $data     = array(
                            'id'         => $this->input->post('id'),
                            'from_date'  => $this->input->post('from_date'),
                            'to_date'    => $this->input->post('to_date'),
                            'apply_date' => $this->input->post('apply_date'),
                            'reason'     => $this->input->post('reason'),
                        );
                        $upload_path = $this->config->item('upload_path') . "/student_leavedocuments/";

                        $this->leave_model->add($data);
                        if (isset($_FILES["file"]) && !empty($_FILES['file']['name'])) {
                            $fileInfo = pathinfo($_FILES["file"]["name"]);
                            $img_name = $leave_id . '.' . $fileInfo['extension'];
                            move_uploaded_file($_FILES["file"]["tmp_name"], $upload_path . $img_name);
                            $data = array('id' => $leave_id, 'docs' => $img_name);
                            $this->leave_model->add($data);
                        }

                        $array = array('status' => '1', 'msg' => 'Success');
                    }
                    json_output(200, $array);
                }
            }

        }
    }

    public function deleteLeave()
    {
        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params   = json_decode(file_get_contents('php://input'), true);
                    $leave_id = $params['leave_id'];
                    $this->leave_model->delete($leave_id);

                    json_output($response['status'], array('result' => 'Success'));
                    // json_output($response['status'], $response);
                }
            }

        }
    }
    public function getSchoolDetails()
    {
        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {

                    $result                   = $this->setting_model->getSchoolDisplay();
                    $result->start_month_name = ucfirst($this->customlib->getMonthList($result->start_month));

                    json_output($response['status'], $result);
                    // json_output($response['status'], $response);
                }
            }

        }
    }

   public function getStudentProfile()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params    = json_decode(file_get_contents('php://input'), true);
                    $studentId = $params['student_id'];

                    $student_fields = $this->setting_model->student_fields();
                    $custom_fields  = $this->customfield_model->student_fields();
                  
                    $student_array                   = array();
                    $student_result = $this->student_model->get($studentId);
                    $student_array['student_result'] = $student_result;
                    $student_array['student_fields'] = $student_fields;

                    if(!empty($custom_fields)){
                      foreach ($custom_fields as $custom_key => $custom_value) {
                           
                      $custom_fields[$custom_key]= $student_result->{$custom_key};
                         
                        }
                    }
                    $student_array['custom_fields']  = $custom_fields;
                    json_output($response['status'], $student_array);
                }
            }
        }
    }


    public function addTask()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {

                $_POST = json_decode(file_get_contents("php://input"), true);
                $this->form_validation->set_data($_POST);
                $this->form_validation->set_error_delimiters('', '');
                $this->form_validation->set_rules('event_title', 'Title', 'required|trim');
                $this->form_validation->set_rules('date', 'Date', 'required|trim');
                $this->form_validation->set_rules('user_id', 'user login id', 'required|trim');

                if ($this->form_validation->run() == false) {

                    $sss = array(
                        'event_title' => form_error('event_title'),
                        'date'        => form_error('date'),
                        'user_id'     => form_error('user_id'),
                    );
                    $array = array('status' => '0', 'error' => $sss);
                    // echo json_encode($array);

                } else {
                    //==================
                    $data = array(
                        'event_title' => $this->input->post('event_title'),
                        'start_date'  => $this->input->post('date'),
                        'end_date'    => $this->input->post('date'),
                        'event_type'  => 'task',
                        'is_active'   => 'no',
                        'event_for'   => $this->input->post('user_id'),
                        'event_color' => '#000',

                    );
                    $this->event_model->saveEvent($data);
                    $array = array('status' => '1', 'msg' => 'Success');
                }
                json_output(200, $array);
            }

        }
    }

    public function updatetask()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {

                $_POST = json_decode(file_get_contents("php://input"), true);
                $this->form_validation->set_data($_POST);
                $this->form_validation->set_error_delimiters('', '');
                $this->form_validation->set_rules('task_id', 'Task ID', 'required|trim');
                $this->form_validation->set_rules('status', 'Status', 'required|trim');

                if ($this->form_validation->run() == false) {
                    $errors = array(
                        'task_id' => form_error('task_id'),
                        'status'  => form_error('status'),
                    );
                    $array = array('status' => '0', 'error' => $errors);
                    // echo json_encode($array);

                } else {
                    //==================
                    $data = array(
                        'id'        => $this->input->post('task_id'),
                        'is_active' => $this->input->post('status'),

                    );
                    $this->event_model->saveEvent($data);
                    $array = array('status' => '1', 'msg' => 'Success');
                }
                json_output(200, $array);
            }

        }
    }

    public function deletetask()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {

                $_POST = json_decode(file_get_contents("php://input"), true);
                $this->form_validation->set_data($_POST);
                $this->form_validation->set_error_delimiters('', '');
                $this->form_validation->set_rules('task_id', 'Task ID', 'required|trim');

                if ($this->form_validation->run() == false) {

                    $errors = array(
                        'task_id' => form_error('task_id'),

                    );
                    $array = array('status' => '0', 'error' => $errors);
                    // echo json_encode($array);

                } else {
                    //==================

                    $id = $this->input->post('task_id');

                    $this->event_model->deleteEvent($id);
                    $array = array('status' => '1', 'msg' => 'Success');
                }
                json_output(200, $array);
            }

        }
    }

    


    public function getTask()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params  = json_decode(file_get_contents('php://input'), true);
                    $user_id = $params['user_id'];
                    $resp    = array();
                    // $student                 = $this->student_model->get($student_id);
                    // $student_login=$this->user_model->getUserLoginDetails($student_id);

                    $resp['tasks'] = $this->event_model->getTask($user_id);

                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getDocument()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST       = json_decode(file_get_contents("php://input"), true);
                    $student_id  = $this->input->post('student_id');
                    $student_doc = $this->student_model->getstudentdoc($student_id);
                    json_output($response['status'], $student_doc);
                }
            }
        }
    }

    public function getHomework()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST      = json_decode(file_get_contents("php://input"), true);
                    $student_id = $this->input->post('student_id');
                    $result     = $this->student_model->get($student_id);

                    $class_id             = $result->class_id;
                    $section_id           = $result->section_id;
                    $section_id           = $result->section_id;
                    $homeworklist         = $this->homework_model->getStudentHomework($class_id, $section_id, $result->student_session_id);
                    $data["homeworklist"] = $homeworklist;
                    $data["class_id"]     = $class_id;
                    $data["section_id"]   = $section_id;
                    $data["subject_id"]   = "";

                    json_output($response['status'], $data);
                }
            }
        }
    }

    public function addaa()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $data = $this->input->POST();

                    $this->form_validation->set_data($data);
                    $this->form_validation->set_error_delimiters('', '');
                    $this->form_validation->set_rules('student_id', 'Student', 'required|trim');
                    $this->form_validation->set_rules('homework_id', 'Homework', 'required|trim');
                    $this->form_validation->set_rules('message', 'Message', 'required|trim');

                    if (empty($_FILES['file']['name'])) {
                        $this->form_validation->set_rules('file', 'File', 'required|trim');
                    }

                    // $this->form_validation->set_rules('base_url', 'base_url', 'required|trim');

                    if ($this->form_validation->run() == false) {

                        $sss = array(
                            'student_id'  => form_error('student_id'),
                            'homework_id' => form_error('homework_id'),
                            'message'     => form_error('message'),
                            'file'        => form_error('file'),
                        );
                        $array = array('status' => '0', 'error' => $sss);
                        // echo json_encode($array);

                    } else {
                        //==================
                        $upload_path = $this->config->item('upload_path') . "/homework/assignment/";

                        if (isset($_FILES["file"]) && !empty($_FILES['file']['name'])) {
                            $time     = md5($_FILES["file"]['name'] . microtime());
                            $fileInfo = pathinfo($_FILES["file"]["name"]);
                            $img_name = $time . '.' . $fileInfo['extension'];
                            move_uploaded_file($_FILES["file"]["tmp_name"], $upload_path . $img_name);

                            $data_insert = array(
                                'homework_id' => $this->input->post('homework_id'),
                                'student_id'  => $this->input->post('student_id'),
                                'docs'        => $img_name,
                            );

                            $this->homework_model->add($data_insert);
                        }

                        $array = array('status' => '1', 'msg' => 'Success');
                    }
                    json_output(200, $array);
                }
            }

        }
    }

    public function getTimeline()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $student_id = $params['studentId'];
                    $timeline   = $this->timeline_model->getTimeline($student_id);
                    json_output($response['status'], $timeline);
                }
            }
        }
    }
    public function getOnlineExam()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params             = json_decode(file_get_contents('php://input'), true);
                    $student_id         = $params['student_id'];
                    $result             = $this->student_model->get($student_id);
                    $resp['onlineexam'] = $this->onlineexam_model->getStudentexam($result->student_session_id);
                    json_output($response['status'], $resp);
                }
            }
        }
    }
    public function getOnlineExamQuestion()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $student_id = $params['student_id'];
                    $recordid   = $params['online_exam_id'];

                    $result     = $this->student_model->get($student_id);
                    $onlineexam = array();
                    $exam       = $this->onlineexam_model->get($recordid);

                    $onlineexam_student            = $this->onlineexam_model->examstudentsID($result->student_session_id, $exam['id']);
                    $exam['onlineexam_student_id'] = $onlineexam_student->id;
                    $exam['student_session_id']    = $onlineexam_student->student_session_id;
                    $exam['is_submitted']          = $onlineexam_student->is_submitted;

                    $exam['questions']                        = $this->onlineexam_model->getExamQuestions($recordid);
                    $getStudentAttemts                        = $this->onlineexam_model->getStudentAttemts($onlineexam_student->id);
                    $onlineexam['exam_result_publish_status'] = $exam['publish_result'];
                    $onlineexam['exam_attempt_status']        = 0;

                    if (strtotime(date('Y-m-d H:i:s')) >= strtotime(date($exam['exam_to'] . ' 23:59:59'))) {
                        $question_status                   = 1;
                        $onlineexam['exam_attempt_status'] = 1;
                    } else if ($exam['attempt'] > $getStudentAttemts) {
                        $this->onlineexam_model->addStudentAttemts(array('onlineexam_student_id' => $onlineexam_student->id));
                    } else {
                        $question_status                   = 1;
                        $onlineexam['exam_attempt_status'] = 1;
                    }
                    $exam['status'] = $onlineexam;

        // print_r($onlineexam);
                    // exit();
                    json_output($response['status'], array('exam' => $exam));
                }
            }
        }
    }

    public function getOnlineExamResult()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params                = json_decode(file_get_contents('php://input'), true);
                    $onlineexam_student_id = $params['onlineexam_student_id'];
                    $exam_id               = $params['exam_id'];

                    $exam = $this->onlineexam_model->get($exam_id);

                    $resp['question_result'] = $this->onlineexam_model->getResultByStudent($onlineexam_student_id, $exam_id);
                    $correct_ans             = 0;
                    $wrong_ans               = 0;
                    $not_attempted           = 0;
                    $total_question          = 0;
                    if (!empty($resp['question_result'])) {
                        $total_question = count($resp['question_result']);

                        foreach ($resp['question_result'] as $result_key => $question_value) {
                            if ($question_value->select_option != null) {

                                if ($question_value->select_option == $question_value->correct) {
                                    $correct_ans++;
                                } else {
                                    $wrong_ans++;
                                }
                            } else {
                                $not_attempted++;
                            }
                        }
                    }
                    $exam['correct_ans']    = $correct_ans;
                    $exam['wrong_ans']      = $wrong_ans;
                    $exam['not_attempted']  = $not_attempted;
                    $exam['total_question'] = $total_question;
                    $exam['score']          = ($correct_ans * 100) / $total_question;
                    $resp['exam']           = $exam;

                    json_output($response['status'], array('result' => $resp));
                }
            }
        }
    }

    public function saveOnlineExam()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params = json_decode(file_get_contents('php://input'), true);

                    $onlineexam_student_id = $params['onlineexam_student_id'];
                    $rows                  = $params['rows'];
                    $resp                  = array();
                    if (!empty($rows)) {
                        $save_result = array();

                        $insert_result = $this->onlineexam_model->add($rows, $onlineexam_student_id);
                        if ($insert_result == 1) {
                            $resp = array('status' => 1, 'msg' => 'record inserted');
                        } else if ($insert_result == 2) {
                            $resp = array('status' => 2, 'msg' => 'record already submitted');
                        } else if ($insert_result == 0) {
                            $resp = array('status' => 2, 'msg' => 'something wrong');
                        }

                    }

                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getExamList()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params = json_decode(file_get_contents('php://input'), true);

                    $student_id = $params['student_id'];
                    $result     = $this->student_model->get($student_id);

                    $examSchedule = $this->examgroup_model->studentExams($result->student_session_id);

                    $data['examSchedule'] = $examSchedule;

                    json_output($response['status'], $data);
                }
            }
        }
    }

    public function getExamSchedule()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params                = json_decode(file_get_contents('php://input'), true);
                    $exam_id               = $params['exam_group_class_batch_exam_id'];
                    $exam_subjects         = $this->examgroup_model->getExamSubjects($exam_id);
                    $data['exam_subjects'] = $exam_subjects;
                    json_output($response['status'], $data);
                }
            }
        }
    }

    public function getNotifications()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params = json_decode(file_get_contents('php://input'), true);
                    $type   = $params['type'];
                    $resp   = $this->webservice_model->getNotifications($type);
                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getSubjectList()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $class_id   = $params['class_id'];
                    $section_id = $params['section_id'];
                    $resp       = $this->subjecttimetable_model->getSubjects($class_id, $section_id);
                    $subjects   = array();
                    if (!empty($resp)) {

                        foreach ($resp as $res_key => $res_value) {

                            $subjects[] = array(
                                'subject_id' => $res_value->subject_id,
                                'subject'    => $res_value->subject_name,
                                'code'       => $res_value->code,
                                'type'       => $res_value->type,
                            );
                        }

                    }

                    // $resp       = $this->webservice_model->getSubjectList($class_id, $section_id);
                    json_output($response['status'], array('result_list' => $subjects));
                }
            }
        }
    }

    public function getSubjectTimetable()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $class_id   = $params['class_id'];
                    $section_id = $params['section_id'];
                    $subject_id = $params['subject_id'];
                    $resp       = $this->subjecttimetable_model->getSubjectTimetable($class_id, $section_id, $subject_id);
                    $subjects   = array();

                    // $resp       = $this->webservice_model->getSubjectList($class_id, $section_id);
                    json_output($response['status'], array('result_list' => $resp));
                }
            }
        }
    }

    // public function getTeachersList111()
    // {
    //     $method = $this->input->server('REQUEST_METHOD');
    //     if ($method != 'POST') {
    //         json_output(400, array('status' => 400, 'message' => 'Bad request.'));
    //     } else {
    //         $check_auth_client = $this->auth_model->check_auth_client();
    //         if ($check_auth_client == true) {
    //             $response = $this->auth_model->auth();
    //             if ($response['status'] == 200) {
    //                 $params        = json_decode(file_get_contents('php://input'), true);
    //                 $user_id       = $params['user_id'];
    //                 $class_id      = $params['class_id'];
    //                 $section_id    = $params['section_id'];
    //                 $resp          = $this->subjecttimetable_model->getTeachers($class_id, $section_id);
    //                 $class_teacher = array();
    //                 if (!empty($resp)) {

    //                     foreach ($resp as $res_key => $res_value) {

    //                         $rating = $this->subjecttimetable_model->user_rating($user_id, $res_value->staff_id);
    //                         $rate   = 0;
    //                         if ($rating) {
    //                             $rate = $rating->rate;
    //                         }
    //                         $class_teacher[$res_value->staff_id] = array(
    //                             'staff_id'         => $res_value->staff_id,
    //                             'staff_name'       => $res_value->staff_name,
    //                             'staff_surname'    => $res_value->staff_surname,
    //                             'contact_no'       => $res_value->contact_no,
    //                             'class_teacher_id' => $res_value->class_teacher_id,
    //                             'rate'             => $rate,
    //                         );

    //                     }

    //                 }
    //                 // $resp      = $this->webservice_model->getTeachersList($sectionId);
    //                 json_output($response['status'], array('result_list' => $class_teacher));
    //             }
    //         }
    //     }
    // }
    public function getTeachersList()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $user_id    = $params['user_id'];
                    $class_id   = $params['class_id'];
                    $section_id = $params['section_id'];
                    $resp       = $this->subjecttimetable_model->getTeachers($class_id, $section_id);

                    $class_teacher = array();
                    if (!empty($resp)) {

                        foreach ($resp as $res_key => $res_value) {
                            $is_duplicate = false;
                            $rating       = $this->subjecttimetable_model->user_rating($user_id, $res_value->staff_id);
                            $rate         = 0;
                            if ($rating) {
                                $rate = $rating->rate;
                            }

                            if (is_null($res_value->day)) {
                                $total_row = checkDuplicateTeacher($resp, $res_value->staff_id);
                                if ($total_row > 1) {
                                    $is_duplicate = true;

                                }
                            }
                            if (!$is_duplicate) {
                                if (array_key_exists($res_value->staff_id, $class_teacher)) {

                                    $class_teacher[$res_value->staff_id]['subjects'][] = array(
                                        'subject_id'   => $res_value->subject_id,
                                        'subject_name' => $res_value->subject_name,
                                        'code'         => $res_value->code,
                                        'type'         => $res_value->type,
                                        'day'          => $res_value->day,
                                        'time_from'    => $res_value->time_from,
                                        'time_to'      => $res_value->time_to,
                                        'room_no'      => $res_value->room_no,
                                    );
                                } else {

                                    $class_teacher[$res_value->staff_id] = array(
                                        'employee_id'      => $res_value->employee_id,
                                        'staff_id'         => $res_value->staff_id,
                                        'staff_name'       => $res_value->staff_name,
                                        'staff_surname'    => $res_value->staff_surname,
                                        'contact_no'       => $res_value->contact_no,
                                        'email'            => $res_value->email,
                                        'class_teacher_id' => $res_value->class_teacher_id,
                                        'rate'             => $rate,
                                        'subjects'         => array(),
                                    );
                                    if (!is_null($res_value->day)) {
                                        $class_teacher[$res_value->staff_id]['subjects'][] = array(
                                            'subject_id'   => $res_value->subject_id,
                                            'subject_name' => $res_value->subject_name,
                                            'code'         => $res_value->code,
                                            'type'         => $res_value->type,
                                            'day'          => $res_value->day,
                                            'time_from'    => $res_value->time_from,
                                            'time_to'      => $res_value->time_to,
                                            'room_no'      => $res_value->room_no,
                                        );
                                    }

                                }

                                // $class_teacher[] = array(
                                // 'staff_id'         => $res_value->staff_id,
                                // 'staff_name'       => $res_value->staff_name,
                                // 'staff_surname'    => $res_value->staff_surname,
                                // 'contact_no'       => $res_value->contact_no,
                                // 'class_teacher_id' => $res_value->class_teacher_id,
                                // 'subject_id' => $res_value->subject_id,
                                // 'subject_name' => $res_value->subject_name,
                                // 'code' => $res_value->code,
                                // 'type' => $res_value->type,
                                // 'day' => $res_value->day,
                                // 'time_from' => $res_value->time_from,
                                // 'time_to' => $res_value->time_to,
                                // 'room_no' => $res_value->room_no,
                                // 'rate'             => $rate,
                                // );
                            }

                        }

                    }
                    // $resp      = $this->webservice_model->getTeachersList($sectionId);
                    json_output($response['status'], array('result_list' => $class_teacher));
                }
            }
        }
    }

    public function getClassTimetable()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $user_id    = $params['user_id'];
                    $class_id   = $params['class_id'];
                    $section_id = $params['section_id'];
                    $resp       = $this->subjecttimetable_model->getTeachers($class_id, $section_id);

                    $class_teacher = array();
                    if (!empty($resp)) {

                        foreach ($resp as $res_key => $res_value) {
                            $is_duplicate = false;
                            $rating       = $this->subjecttimetable_model->user_rating($user_id, $res_value->staff_id);
                            $rate         = 0;
                            if ($rating) {
                                $rate = $rating->rate;
                            }

                            if (is_null($res_value->day)) {
                                $total_row = checkDuplicateTeacher($resp, $res_value->staff_id);
                                if ($total_row > 1) {
                                    $is_duplicate = true;

                                }
                            }
                            if (!$is_duplicate) {

                                $class_teacher[] = array(
                                    'staff_id'         => $res_value->staff_id,
                                    'staff_name'       => $res_value->staff_name,
                                    'staff_surname'    => $res_value->staff_surname,
                                    'contact_no'       => $res_value->contact_no,
                                    'class_teacher_id' => $res_value->class_teacher_id,
                                    'subject_id'       => $res_value->subject_id,
                                    'subject_name'     => $res_value->subject_name,
                                    'code'             => $res_value->code,
                                    'type'             => $res_value->type,
                                    'day'              => $res_value->day,
                                    'time_from'        => $res_value->time_from,
                                    'time_to'          => $res_value->time_to,
                                    'room_no'          => $res_value->room_no,
                                    'rate'             => $rate,
                                );
                            }

                        }

                    }
                    // $resp      = $this->webservice_model->getTeachersList($sectionId);
                    json_output($response['status'], array('result_list' => $class_teacher));
                }
            }
        }
    }

    public function getTeacherSubject()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params = json_decode(file_get_contents('php://input'), true);

                    $staff_id   = $params['staff_id'];
                    $class_id   = $params['class_id'];
                    $section_id = $params['section_id'];
                    $resp       = $this->subjecttimetable_model->getTeacherSubject($class_id, $section_id, $staff_id);

                    // $resp      = $this->webservice_model->getTeachersList($sectionId);
                    json_output($response['status'], array('result_list' => $resp));
                }
            }
        }
    }

    public function addStaffRating()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {

                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params = json_decode(file_get_contents('php://input'), true);
                    $data   = array(
                        'user_id'  => $params['user_id'],
                        'staff_id' => $params['staff_id'],
                        'rate'     => $params['rate'],
                        'comment'  => $params['comment'],
                        'role'     => 'student',

                    );

                    $insert_result = $this->subjecttimetable_model->add_rating($data);
                    if ($insert_result) {
                        $resp = array('status' => 1, 'msg' => 'inserted');
                    } else {
                        $resp = array('status' => 0, 'msg' => 'something wrong or already submitted');
                    }

                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getLibraryBooks()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'GET') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {

                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $resp = $this->webservice_model->getLibraryBooks();
                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getLibraryBookIssued()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {

                    $params      = json_decode(file_get_contents('php://input'), true);
                    $studentId   = $params['studentId'];
                    $member_type = "student";
                    $resp        = $this->librarymember_model->checkIsMember($member_type, $studentId);

                    json_output($response['status'], $resp);
                }
            }
        }
    }
    // public function getTransportRoute()
    // {
    //     $method = $this->input->server('REQUEST_METHOD');
    //     if ($method != 'GET') {
    //         json_output(400, array('status' => 400, 'message' => 'Bad request.'));
    //     } else {
    //         $check_auth_client = $this->auth_model->check_auth_client();
    //         if ($check_auth_client == true) {
    //             $response = $this->auth_model->auth();
    //             if ($response['status'] == 200) {
    //                 $resp = $this->webservice_model->getTransportRoute();
    //                 json_output($response['status'], $resp);
    //             }
    //         }
    //     }
    // }

    public function getTransportroute()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {

                    $params       = json_decode(file_get_contents('php://input'), true);
                    $student_id   = $params['student_id'];
                    $student      = $this->student_model->get($student_id);
                    $vec_route_id = $student->vehroute_id;
                    $listroute    = $this->vehroute_model->listroute();

                    if ($vec_route_id != "") {
                        if (!empty($listroute)) {
                            foreach ($listroute as $listroute_key => $listroute_value) {

                                if (!empty($listroute_value['vehicles'])) {
                                    foreach ($listroute_value['vehicles'] as $route_key => $route_value) {
                                        if ($route_value->vec_route_id == $vec_route_id) {
                                            $route_value->assigned = "yes";
                                            break;
                                        } else {
                                            $route_value->assigned = "no";
                                        }

                                    }
                                }
                            }

                        }

                    }

                    json_output($response['status'], $listroute);
                }
            }
        }
    }

    public function getHostelList()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'GET') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $resp = $this->webservice_model->getHostelList();
                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getHostelDetails()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $hostelId   = $params['hostelId'];
                    $student_id = $params['student_id'];
                    $resp       = $this->webservice_model->getHostelDetails($hostelId, $student_id);
                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getDownloadsLinks()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params    = json_decode(file_get_contents('php://input'), true);
                    $tag       = $params['tag'];
                    $classId   = $params['classId'];
                    $sectionId = $params['sectionId'];
                    $resp      = $this->webservice_model->getDownloadsLinks($classId, $sectionId, $tag);
                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getTransportVehicleDetails()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params    = json_decode(file_get_contents('php://input'), true);
                    $vehicleId = $params['vehicleId'];
                    $resp      = $this->webservice_model->getTransportVehicleDetails($vehicleId);
                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function getAttendenceRecords1()
    {
        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    ///===================
                    $_POST = json_decode(file_get_contents("php://input"), true);

                    $year       = $this->input->post('year');
                    $month      = $this->input->post('month');
                    $student_id = $this->input->post('student_id');
                    $student    = $this->student_model->get($student_id);

                    $student_session_id = $student['student_session_id'];
                    $result             = array();
                    $new_date           = "01-" . $month . "-" . $year;

                    $totalDays            = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $first_day_this_month = date('01-m-Y');
                    $fst_day_str          = strtotime(date($new_date));
                    $array                = array();
                    for ($day = 2; $day <= $totalDays; $day++) {
                        $fst_day_str        = ($fst_day_str + 86400);
                        $date               = date('Y-m-d', $fst_day_str);
                        $student_attendence = $this->attendencetype_model->getStudentAttendence($date, $student_session_id);
                        if (!empty($student_attendence)) {
                            $s         = array();
                            $s['date'] = $date;
                            $type      = $student_attendence->type;
                            $s['type'] = $type;
                            $array[]   = $s;
                        }
                    }
                    $data['status'] = 200;
                    $data['data']   = $array;
                    json_output($response['status'], $data);

                    //======================
                }
            }
        }
    }

    public function getAttendenceRecords()
    {
        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $school_setting = $this->setting_model->getSchoolDetail();

                    $_POST                   = json_decode(file_get_contents("php://input"), true);
                    $year                    = $this->input->post('year');
                    $month                   = $this->input->post('month');
                    $student_id              = $this->input->post('student_id');
                    $date                    = $this->input->post('date');
                    $student                 = $this->student_model->get($student_id);
                    $student_session_id      = $student->student_session_id;
                    $data                    = array();
                    $data['attendence_type'] = $school_setting->attendence_type;
                    if ($school_setting->attendence_type) {
                        $timestamp         = strtotime($date);
                        $day               = date('l', $timestamp);
                        $attendence_result = $this->attendencetype_model->studentAttendanceByDate($student->class_id, $student->section_id, $day, $date, $student_session_id);
                        $data['data']      = $attendence_result;

                    } else {

                        $result   = array();
                        $new_date = "01-" . $month . "-" . $year;

                        $totalDays            = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                        $first_day_this_month = date('01-m-Y');
                        $fst_day_str          = strtotime(date($new_date));
                        $array                = array();

                        for ($day = 1; $day <= $totalDays; $day++) {
                            $date               = date('Y-m-d', $fst_day_str);
                            $student_attendence = $this->attendencetype_model->getStudentAttendence($date, $student_session_id);
                            if (!empty($student_attendence)) {
                                $s         = array();
                                $s['date'] = $date;
                                $type      = $student_attendence->type;
                                $s['type'] = $type;
                                $array[]   = $s;
                            }
                            $fst_day_str = ($fst_day_str + 86400);
                        }

                        $data['data'] = $array;
                    }

                    json_output($response['status'], $data);

                    //======================
                }
            }
        }
    }

    public function examSchedule()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST = json_decode(file_get_contents("php://input"), true);

                    $student_id           = $this->input->post('student_id');
                    $data                 = array();
                    $stu_record           = $this->student_model->getRecentRecord($student_id);
                    $data['status']       = "200";
                    $data['class_id']     = $stu_record['class_id'];
                    $data['section_id']   = $stu_record['section_id'];
                    $examSchedule         = $this->examschedule_model->getExamByClassandSection($data['class_id'], $data['section_id']);
                    $data['examSchedule'] = $examSchedule;
                    json_output($response['status'], $data);
                }
            }
        }
    }

    public function getexamscheduledetail()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST = json_decode(file_get_contents("php://input"), true);
                    $this->form_validation->set_data($_POST);
                    $exam_id      = $this->input->post('exam_id');
                    $section_id   = $this->input->post('section_id');
                    $class_id     = $this->input->post('class_id');
                    $examSchedule = $this->examschedule_model->getDetailbyClsandSection($class_id, $section_id, $exam_id);
                    json_output($response['status'], $examSchedule);
                }
            }
        }

    }

    public function getsyllabus()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST = json_decode(file_get_contents("php://input"), true);
                    $this->form_validation->set_data($_POST);
                   
                    $subject_group_subject_id        = $this->input->post('subject_group_subject_id');
                    $subject_group_class_sections_id = $this->input->post('subject_group_class_sections_id');
                    $time_from                       = $this->input->post('time_from');
                    $time_to                         = $this->input->post('time_to');
                    $date                            = $this->input->post('date');
                    $syllabus['data']            = $this->syllabus_model->getDetailbyDateandTime($subject_group_subject_id, $subject_group_class_sections_id, $time_from, $time_to, $date);
                    json_output($response['status'], $syllabus);
                }
            }
        }

    }

    //custom syllabus api
    public function getsyllabusAll()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST = json_decode(file_get_contents("php://input"), true);
                    $this->form_validation->set_data($_POST);
                   
                    $subject_group_subject_id        = $this->input->post('subject_group_subject_id');
                    $subject_group_class_sections_id = $this->input->post('subject_group_class_sections_id');
                    $time_from                       = $this->input->post('time_from');
                    $time_to                         = $this->input->post('time_to');
                    $date                            = $this->input->post('date');
                    $date1                           = $this->input->post('date1');
                    $syllabus['data']            = $this->syllabus_model->getDetailbyDateandTime1($subject_group_subject_id, $subject_group_class_sections_id, $time_from, $time_to, $date,$date1);
                    json_output($response['status'], $syllabus);
                }
            }
        }

    }
    //custom syllabus api

    public function getsyllabussubjects()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST = json_decode(file_get_contents("php://input"), true);
                    $this->form_validation->set_data($_POST);
                    $student_id         = $this->input->post('student_id');
                    $stu_record         = $this->student_model->getRecentRecord($student_id);
                    $data['class_id']   = $stu_record['class_id'];
                    $data['section_id'] = $stu_record['section_id'];
                    $subjects['subjects']          = $this->syllabus_model->getSyllabusSubjects($data['class_id'], $data['section_id']);
                    json_output($response['status'], $subjects);
                }
            }
        }

    }

    public function getSubjectsLessons()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST = json_decode(file_get_contents("php://input"), true);
                    $this->form_validation->set_data($_POST);
                    $subject_group_subject_id         = $this->input->post('subject_group_subject_id');
                     $subject_group_class_sections_id         = $this->input->post('subject_group_class_sections_id');                
                  
                    $subjects          = $this->syllabus_model->getSubjectsLesson($subject_group_subject_id,$subject_group_class_sections_id);
                    json_output($response['status'], $subjects);
                }
            }
        }

    }

    // public function fees()
    // {
    //     $method = $this->input->server('REQUEST_METHOD');

    //     if ($method != 'POST') {
    //         json_output(400, array('status' => 400, 'message' => 'Bad request.'));
    //     } else {

    //         $check_auth_client = $this->auth_model->check_auth_client();
    //         if ($check_auth_client == true) {
    //             $response = $this->auth_model->auth();
    //             if ($response['status'] == 200) {

    //                 $_POST      = json_decode(file_get_contents("php://input"), true);
    //                 $student_id = $this->input->post('student_id');

    //                 $student = $this->student_model->get($student_id);
    //                 // $studentSession     = $this->student_model->getStudentSession($student_id);
    //                 // $student_session_id = $studentSession["student_session_id"];
    //                 // $student_session    = $studentSession["session"];

    //                 $student_due_fee              = $this->studentfeemaster_model->getStudentFees($student['student_session_id']);
    //                 $student_discount_fee         = $this->feediscount_model->getStudentFeesDiscount($student['student_session_id']);
    //                 $data['student_due_fee']      = $student_due_fee;
    //                 $data['student_discount_fee'] = $student_discount_fee;
    //                 json_output($response['status'], $data);
    //             }
    //         }

    //     }
    // }

      public function fees()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $data       = array();
                    $pay_method = $this->paymentsetting_model->getActiveMethod();
                    $_POST      = json_decode(file_get_contents("php://input"), true);
                    $student_id = $this->input->post('student_id');
                    $student    = $this->student_model->get($student_id);

                    $student_due_fee      = $this->studentfeemaster_model->getStudentFees($student->student_session_id);
                    $student_discount_fee = $this->feediscount_model->getStudentFeesDiscount($student->student_session_id);
                    $init_amt             = 0;
                    $grand_amt            = 0;
                    $grand_total_paid     = 0;
                    $grand_total_discount = 0;
                    $grand_total_fine     = 0;

                    if (!empty($student_due_fee)) {

                        foreach ($student_due_fee as $student_due_fee_key => $student_due_fee_value) {

                            foreach ($student_due_fee_value->fees as $each_fees_key => $each_fees_value) {

                                $amt                                     = 0;
                                $total_paid                              = 0;
                                $total_discount                          = 0;
                                $total_fine                              = 0;
                                $each_fees_value->total_amount_paid      = number_format((float) $amt, 2, '.', '');
                                $each_fees_value->total_amount_discount  = number_format((float) $amt, 2, '.', '');
                                $each_fees_value->total_amount_fine      = number_format((float) $amt, 2, '.', '');
                                $each_fees_value->total_amount_display   = number_format((float) $amt, 2, '.', '');
                                $each_fees_value->total_amount_remaining = number_format((float) $each_fees_value->amount, 2, '.', '');
                                $each_fees_value->status                 = 'unpaid';

                                $grand_amt = $grand_amt + $each_fees_value->amount;

                                if (is_string($each_fees_value->amount_detail) && is_array(json_decode($each_fees_value->amount_detail, true)) && (json_last_error() == JSON_ERROR_NONE)) {
                                    $fess_list = json_decode($each_fees_value->amount_detail);

                                    foreach ($fess_list as $fee_key => $fee_value) {

                                        $grand_total_paid = $grand_total_paid + $fee_value->amount;
                                        $total_paid       = $total_paid + $fee_value->amount;

                                        $grand_total_discount = $grand_total_discount + $fee_value->amount_discount;
                                        $total_discount       = $total_discount + $fee_value->amount_discount;

                                        $grand_total_fine = $grand_total_fine + $fee_value->amount_fine;
                                        $total_fine       = $total_fine + $fee_value->amount_fine;

                                    }

                                    $each_fees_value->total_amount_paid     = number_format((float) $total_paid, 2, '.', '');
                                    $each_fees_value->total_amount_discount = number_format((float) $total_discount, 2, '.', '');
                                    $each_fees_value->total_amount_fine     = number_format((float) $total_fine, 2, '.', '');

                                    $each_fees_value->total_amount_display   = number_format((float) ($total_paid + $total_discount), 2, '.', '');
                                    $each_fees_value->total_amount_remaining = number_format((float) ($each_fees_value->amount - (($total_paid + $total_discount))), 2, '.', '');

                                    if ($each_fees_value->total_amount_remaining <= '0.00') {
                                        $each_fees_value->status = 'paid';
                                    } elseif ($each_fees_value->total_amount_remaining == number_format((float) $each_fees_value->amount, 2, '.', '')) {
                                        $each_fees_value->status = 'unpaid';
                                    } else {
                                        $each_fees_value->status = 'partial';

                                    }
                                }

                                if (($each_fees_value->amount - ($each_fees_value->total_amount_paid + $each_fees_value->total_amount_discount)) == 0) {
                                    $each_fees_value->status = 'paid';
                                }
                            }
                        }
                    }

                    $grand_fee = array('amount' => number_format((float) $grand_amt, 2, '.', ''), 'amount_discount' => number_format((float) $grand_total_discount, 2, '.', ''), 'amount_fine' => number_format((float) $grand_total_fine, 2, '.', ''), 'amount_paid' => number_format((float) $grand_total_paid, 2, '.', ''), 'amount_remaining' => number_format((float) ($grand_amt - ($grand_total_paid + $grand_total_discount)), 2, '.', ''));

                    $data['pay_method']           = empty($pay_method) ? 0 : 1;
                    $data['student_due_fee']      = $student_due_fee;
                    $data['student_discount_fee'] = $student_discount_fee;
                    $data['grand_fee']            = $grand_fee;
                    json_output($response['status'], $data);
                }
            }
        }
    }
    // public function class_schedule()
    //    {
    //        $method = $this->input->server('REQUEST_METHOD');
    //        if ($method != 'POST') {
    //            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
    //        } else {
    //            $check_auth_client = $this->auth_model->check_auth_client();
    //            if ($check_auth_client == true) {
    //                $response = $this->auth_model->auth();
    //                if ($response['status'] == 200) {
    //                    $_POST                   = json_decode(file_get_contents("php://input"), true);
    //                    $student_id              = $this->input->post('student_id');
    //                    $student                 = $this->student_model->get($student_id);
    //                    $class_id                = $student['class_id'];
    //                    $section_id              = $student['section_id'];
    //                    $data['class_id']        = $class_id;
    //                    $data['section_id']      = $section_id;
    //                    $result_subjects         = $this->teachersubject_model->getSubjectByClsandSection($class_id, $section_id);
    //                    $getDaysnameList         = $this->customlib->getDaysname();
    //                    $data['getDaysnameList'] = $getDaysnameList;
    //                    $dayListArray            = array();

    //                    foreach ($getDaysnameList as $Day_key => $Day_value) {
    //                        $dayListArray[$Day_value] = array();
    //                    }

    //                    $final_array = array();
    //                    if (!empty($result_subjects)) {
    //                        foreach ($result_subjects as $subject_k => $subject_v) {

    //                            foreach ($getDaysnameList as $day_key => $day_value) {
    //                                $where_array = array(
    //                                    'teacher_subject_id' => $subject_v['id'],
    //                                    'day_name'           => $day_value,
    //                                );
    //                                $obj    = new stdClass();
    //                                $result = $this->timetable_model->get($where_array);
    //                                if (!empty($result)) {
    //                                    $obj->status     = "Yes";
    //                                    $obj->start_time = $result[0]['start_time'];
    //                                    $obj->end_time   = $result[0]['end_time'];
    //                                    $obj->room_no    = $result[0]['room_no'];
    //                                    $obj->subject    = $subject_v['name'];
    //                                } else {

    //                                    $obj->status     = "No";
    //                                    $obj->start_time = "N/A";
    //                                    $obj->end_time   = "N/A";
    //                                    $obj->room_no    = "N/A";
    //                                    $obj->subject    = $subject_v['name'];

    //                                }

    //                                $dayListArray[$day_value][] = $obj;
    //                            }
    //                        }
    //                    }

    //                    $data['status']       = "200";
    //                    $data['result_array'] = array();
    //                    $data['result_array'] = $dayListArray;
    //                    json_output($response['status'], $data);
    //                }
    //            }
    //        }
    //    }

    public function class_schedule()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST      = json_decode(file_get_contents("php://input"), true);
                    $student_id = $this->input->post('student_id');
                    $student    = $this->student_model->get($student_id);
                    $class_id   = $student->class_id;
                    $section_id = $student->section_id;
                    // $data['class_id']   = $class_id;
                    // $data['section_id'] = $section_id;

                    $days        = $this->customlib->getDaysname();
                    $days_record = array();
                    foreach ($days as $day_key => $day_value) {

                        $days_record[$day_key] = $this->subjecttimetable_model->getSubjectByClassandSectionDay($class_id, $section_id, $day_key);
                    }
                    $data['timetable'] = $days_record;
                    $data['status']    = "200";
                    json_output($response['status'], $data);
                }
            }
        }
    }

    // public function getExamResultList()
    // {
    //     $method = $this->input->server('REQUEST_METHOD');
    //     if ($method != 'POST') {
    //         json_output(400, array('status' => 400, 'message' => 'Bad request.'));
    //     } else {
    //         $check_auth_client = $this->auth_model->check_auth_client();
    //         if ($check_auth_client == true) {
    //             $response = $this->auth_model->auth();
    //             if ($response['status'] == 200) {
    //                 $_POST      = json_decode(file_get_contents("php://input"), true);
    //                 $student_id = $this->input->post('student_id');
    //                 $student    = $this->student_model->get($student_id);
    //                 $examList   = $this->examschedule_model->getExamByClassandSection($student['class_id'], $student['section_id']);
    //                 $resp['status'] = 200;
    //                 $resp['examList'] = $examList;
    //                 json_output(200, $resp);
    //             }
    //         }
    //     }
    // }

    // public function getExamResultList()
    // {
    //     $method = $this->input->server('REQUEST_METHOD');
    //     if ($method != 'POST') {
    //         json_output(400, array('status' => 400, 'message' => 'Bad request.'));
    //     } else {
    //         $check_auth_client = $this->auth_model->check_auth_client();
    //         if ($check_auth_client == true) {
    //             $response = $this->auth_model->auth();
    //             if ($response['status'] == 200) {
    //                 $_POST          = json_decode(file_get_contents("php://input"), true);
    //                 $student_id     = $this->input->post('student_id');
    //                 $student        = $this->student_model->get($student_id);
    //                 $examList       = $this->examschedule_model->getExamByClassandSection($student['class_id'], $student['section_id']);
    //                 $resp['status'] = 200;

    //                 $resp['examList'] = array();
    //                 if (!empty($examList)) {
    //                     $new_array = array();
    //                     foreach ($examList as $ex_key => $ex_value) {
    //                         $array   = array();
    //                         $x       = array();
    //                         $exam_id = $ex_value['exam_id'];
    //                         $student['id'];
    //                         $exam_subjects = $this->examschedule_model->getresultByStudentandExam($exam_id, $student['id']);
    //                         $total_marks   = 0;
    //                         $get_marks     = 0;
    //                         $result        = "Pass";

    //                         foreach ($exam_subjects as $key => $value) {

    //                             $total_marks = $total_marks + $value['full_marks'];
    //                             $get_marks   = $get_marks + $value['get_marks'];

    //                             if (($value['get_marks'] < $value['passing_marks']) || ($value['attendence'] != 'pre')) {
    //                                 $result = 'Fail';
    //                             }

    //                         }

    //                         $exam_result              = new stdClass();
    //                         $exam_result->total_marks = $total_marks;
    //                         $exam_result->get_marks   = number_format($get_marks, 2);
    //                         $exam_result->percentage  = number_format((($get_marks * 100) / $total_marks), 2) . '%';
    //                         $exam_result->grade       = $this->getGradeByMarks(number_format((($get_marks * 100) / $total_marks), 2));
    //                         $exam_result->result      = $result;
    //                         $exam_result->exam_id     = $ex_value['exam_id'];
    //                         $array['exam_name']       = $ex_value['name'];
    //                         $array['exam_result']     = $exam_result;
    //                         $new_array[]              = $array;
    //                     }
    //                     $resp['examList'] = $new_array;
    //                 }

    //                 json_output(200, $resp);
    //             }
    //         }
    //     }
    // }

    public function getExamResult()
    {

        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST                          = json_decode(file_get_contents("php://input"), true);
                    $exam_group_class_batch_exam_id = $this->input->post('exam_group_class_batch_exam_id');
                    $student_id                     = $this->input->post('student_id');
                    $student                        = $this->student_model->get($student_id);

                    $dt          = array();
                    $exam_result = $this->examgroup_model->searchExamResult($student->student_session_id, $exam_group_class_batch_exam_id, true, true);

                    $exam_grade = $this->grade_model->getGradeDetails();

                    if (!empty($exam_result->exam_result)) {
                        $exam                                 = new stdClass;
                        $exam->exam_group_class_batch_exam_id = $exam_result->exam_group_class_batch_exam_id;
                        $exam->exam_group_id                  = $exam_result->exam_group_id;
                        $exam->exam                           = $exam_result->exam;
                        $exam->exam_group                     = $exam_result->name;
                        $exam->description                    = $exam_result->description;
                        $exam->exam_type                      = $exam_result->exam_type;
                        $exam->subject_result                 = array();
                        $exam->total_max_marks                = 0;
                        $exam->total_get_marks                = 0;
                        $exam->total_exam_points              = 0;
                        $exam->exam_quality_points            = 0;
                        $exam->exam_credit_hour               = 0;
                        $exam->exam_credit_hour               = 0;
                        $exam->exam_result_status             = "pass";
                        if ($exam_result->exam_result['exam_connection'] == 0) {
                            $exam->is_consolidate = 0;
                            foreach ($exam_result->exam_result['result'] as $exam_result_key => $exam_result_value) {

                                $subject_array = array();
                                if ($exam_result_value->attendence != "present") {
                                    $exam->exam_result_status = "fail";

                                } elseif ($exam_result_value->get_marks < $exam_result_value->min_marks) {

                                    $exam->exam_result_status = "fail";
                                }
                                $exam->total_max_marks = $exam->total_max_marks + $exam_result_value->max_marks;
                                $exam->total_get_marks = $exam->total_get_marks + $exam_result_value->get_marks;
                                // $subject_array['']=$exam_result_value->id;
                                $percentage                                       = ($exam_result_value->get_marks * 100) / $exam_result_value->max_marks;
                                $subject_array['name']                            = $exam_result_value->name;
                                $subject_array['code']                            = $exam_result_value->code;
                                $subject_array['exam_group_class_batch_exams_id'] = $exam_result_value->exam_group_class_batch_exams_id;
                                $subject_array['room_no']                         = $exam_result_value->room_no;
                                $subject_array['max_marks']                       = $exam_result_value->max_marks;
                                $subject_array['min_marks']                       = $exam_result_value->min_marks;
                                $subject_array['subject_id']                      = $exam_result_value->subject_id;
                                $subject_array['attendence']                      = $exam_result_value->attendence;
                                $subject_array['get_marks']                       = $exam_result_value->get_marks;

                                $subject_array['exam_group_exam_results_id'] = $exam_result_value->exam_group_exam_results_id;
                                $subject_array['note']                       = $exam_result_value->note;
                                $subject_array['duration']                   = $exam_result_value->duration;
                                $subject_array['credit_hours']               = $exam_result_value->credit_hours;
                                $subject_array['exam_grade']                 = findExamGrade($exam_grade, $exam_result->exam_type, $percentage);

                                if ($exam_result->exam_type == "gpa") {

                                    $point                                = findGradePoints($exam_grade, $exam_result->exam_type, $percentage);
                                    $exam->exam_quality_points            = $exam->exam_quality_points + ($exam_result_value->credit_hours * $point);
                                    $exam->exam_credit_hour               = $exam->exam_credit_hour + $exam_result_value->credit_hours;
                                    $exam->total_exam_points              = $exam->total_exam_points + $point;
                                    $subject_array['exam_grade_point']    = number_format($point, 2, '.', '');
                                    $subject_array['exam_quality_points'] = $exam_result_value->credit_hours * $point;
                                }
                                $exam->subject_result[] = $subject_array;
                            }
                            $exam->percentage = ($exam->total_get_marks * 100) / $exam->total_max_marks;
                            $exam->division   = getExamDivision($exam->percentage);

                        } else {
                            // print_r($exam_result);
                            $exam->is_consolidate = 1;
                            $exam_connected_exam  = ($exam_result->exam_result['exam_result']['exam_result_' . $exam_result->exam_group_class_batch_exam_id]);

                            if (!empty($exam_connected_exam)) {
                                foreach ($exam_connected_exam as $exam_result_key => $exam_result_value) {

                                    $subject_array = array();
                                    if ($exam_result_value->attendence != "present") {
                                        $exam->exam_result_status = "fail";

                                    } elseif ($exam_result_value->get_marks < $exam_result_value->min_marks) {

                                        $exam->exam_result_status = "fail";
                                    }
                                    $exam->total_max_marks = $exam->total_max_marks + $exam_result_value->max_marks;
                                    $exam->total_get_marks = $exam->total_get_marks + $exam_result_value->get_marks;
                                    // $subject_array['']=$exam_result_value->id;
                                    $percentage                                       = ($exam_result_value->get_marks * 100) / $exam_result_value->max_marks;
                                    $subject_array['name']                            = $exam_result_value->name;
                                    $subject_array['code']                            = $exam_result_value->code;
                                    $subject_array['exam_group_class_batch_exams_id'] = $exam_result_value->exam_group_class_batch_exams_id;
                                    $subject_array['room_no']                         = $exam_result_value->room_no;
                                    $subject_array['max_marks']                       = $exam_result_value->max_marks;
                                    $subject_array['min_marks']                       = $exam_result_value->min_marks;
                                    $subject_array['subject_id']                      = $exam_result_value->subject_id;
                                    $subject_array['attendence']                      = $exam_result_value->attendence;
                                    $subject_array['get_marks']                       = $exam_result_value->get_marks;

                                    $subject_array['exam_group_exam_results_id'] = $exam_result_value->exam_group_exam_results_id;
                                    $subject_array['note']                       = $exam_result_value->note;
                                    $subject_array['duration']                   = $exam_result_value->duration;
                                    $subject_array['credit_hours']               = $exam_result_value->credit_hours;
                                    $subject_array['exam_grade']                 = findExamGrade($exam_grade, $exam_result->exam_type, $percentage);

                                    if ($exam_result->exam_type == "gpa") {

                                        $point                             = findGradePoints($exam_grade, $exam_result->exam_type, $percentage);
                                        $exam->exam_quality_points         = $exam->exam_quality_points + ($exam_result_value->credit_hours * $point);
                                        $exam->exam_credit_hour            = $exam->exam_credit_hour + $exam_result_value->credit_hours;
                                        $exam->total_exam_points           = $exam->total_exam_points + $point;
                                        $subject_array['exam_grade_point'] = number_format($point, 2, '.', '');
                                    }
                                    $exam->subject_result[] = $subject_array;
                                }
                                $exam->percentage = ($exam->total_get_marks * 100) / $exam->total_max_marks;
                                $exam->division   = getExamDivision($exam->percentage);

                            }
                            $consolidate_result                     = new stdClass;
                            $consolidate_get_total                  = 0;
                            $consolidate_total_points               = 0;
                            $consolidate_max_total                  = 0;
                            $consolidate_subjects_total             = 0;
                            $consolidate_result->exam_array         = array();
                            $consolidate_result->consolidate_result = array();
                            $consolidate_result_status              = "pass";
                            if (!empty($exam_result->exam_result['exams'])) {
                                $consolidate_exam_result = "pass";
                                foreach ($exam_result->exam_result['exams'] as $each_exam_key => $each_exam_value) {
                                    if ($exam_result->exam_type != "gpa") {
                                        $consolidate_each = getCalculatedExam($exam_result->exam_result['exam_result'], $each_exam_value->id);

                                        if ($consolidate_each->exam_status == "fail") {
                                            $consolidate_result_status = "fail";
                                        }

                                        $consolidate_get_percentage_mark = getConsolidateRatio($exam_result->exam_result['exam_connection_list'], $each_exam_value->id, $consolidate_each->get_marks);

                                        $each_exam_value->percentage = $consolidate_get_percentage_mark;
                                        $consolidate_get_total       = $consolidate_get_total + ($consolidate_get_percentage_mark);
                                        $consolidate_max_total       = $consolidate_max_total + ($consolidate_each->max_marks);
                                    }

                                    if ($exam_result->exam_type == "gpa") {
                                        $consolidate_each = getCalculatedExamGradePoints($exam_result->exam_result['exam_result'], $each_exam_value->id, $exam_grade, $exam_result->exam_type);

                                        $each_exam_value->total_points = $consolidate_each->total_points;
                                        $each_exam_value->total_exams  = $consolidate_each->total_exams;

                                        $consolidate_exam_result         = ($consolidate_each->total_points / $consolidate_each->total_exams);
                                        $consolidate_get_percentage_mark = getConsolidateRatio($exam_result->exam_result['exam_connection_list'], $each_exam_value->id, $consolidate_exam_result);
                                        $each_exam_value->percentage     = $consolidate_get_percentage_mark;
                                        $consolidate_get_total           = $consolidate_get_total + ($consolidate_get_percentage_mark);

                                        $consolidate_subjects_total = $consolidate_subjects_total + $consolidate_each->total_exams;

                                        $each_exam_value->exam_result = number_format($consolidate_exam_result, 2, '.', '');
                                    }

                                    $consolidate_result->exam_array[] = $each_exam_value;
                                }
                                $consolidate_result->consolidate_result['marks_obtain'] = $consolidate_get_total;
                                $consolidate_result->consolidate_result['marks_total']  = $consolidate_max_total;

                                $consolidate_result->consolidate_result['percentage'] = ($consolidate_get_total * 100) / $consolidate_max_total;
                                $consolidate_result->consolidate_result['division']   = getExamDivision($consolidate_result->consolidate_result['percentage']);
                                if ($exam_result->exam_type != "gpa") {

                                    $consolidate_percentage_grade                            = ($consolidate_get_total * 100) / $consolidate_max_total;
                                    $consolidate_result->consolidate_result['result']        = $consolidate_get_total . "/" . $consolidate_max_total;
                                    $consolidate_result->consolidate_result['grade']         = findExamGrade($exam_grade, $exam_result->exam_type, $consolidate_percentage_grade);
                                    $consolidate_result->consolidate_result['result_status'] = $consolidate_result_status;

                                } elseif ($exam_result->exam_type == "gpa") {
                                    $consolidate_percentage_grade = ($consolidate_get_total * 100) / $consolidate_subjects_total;

                                    $consolidate_result->consolidate_result['result'] = $consolidate_get_total . "/" . $consolidate_subjects_total;

                                    $consolidate_result->consolidate_result['grade'] = findExamGrade($exam_grade, $exam_result->exam_type, $consolidate_percentage_grade);

                                }

                                $consolidate_exam_result_percentage = $consolidate_percentage_grade;

                            }
                            $exam->consolidated_exam_result = $consolidate_result;

                        }
                        $data['exam'] = $exam;
                    }

                    $data['status'] = "200";
                    json_output($response['status'], $data);
                }
            }
        }

    }

    public function getGradeByMarks($marks = 0)
    {
        $gradeList = $this->grade_model->get();
        if (empty($gradeList)) {
            return "empty list";
        } else {

            foreach ($gradeList as $grade_key => $grade_value) {
                if (round($marks) >= $grade_value['mark_from'] && round($marks) <= $grade_value['mark_upto']) {
                    return $grade_value['name'];
                    break;
                }
            }
            return "no record found";
        }
    }

    public function Parent_GetStudentsList()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $array = array();

                    $_POST           = json_decode(file_get_contents("php://input"), true);
                    $parent_id       = $this->input->post('parent_id');
                    $students_array  = $this->student_model->read_siblings_students($parent_id);
                    $array['childs'] = $students_array;
                    json_output($response['status'], $array);
                }
            }
        }

    }

   public function getModuleStatus()
    {
        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST               = json_decode(file_get_contents("php://input"), true);
                    $user                = $this->input->post('user');
                    $resp['module_list'] = $this->module_model->get($user);
                    json_output($response['status'], $resp);
                }
            }

        }
    }

    public function searchuser()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $data = array();

                    $params     = json_decode(file_get_contents('php://input'), true);
                    $student_id = $params['student_id'];

                    $keyword = $params['keyword'];

                    $chat_user    = $this->chatuser_model->getMyID($student_id, 'student');
                    $chat_user_id = 0;
                    if (!empty($chat_user)) {
                        $chat_user_id = $chat_user->id;
                    }

                    $resp['chat_user'] = $this->chatuser_model->searchForUser($keyword, $chat_user_id, 'student', $student_id);
                    json_output($response['status'], $resp);
                }
            }

        }

    }

    public function addChatUser()
    {

        $method = $this->input->server('REQUEST_METHOD');

        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $params      = json_decode(file_get_contents('php://input'), true);
                    $user_type   = $params['user_type'];
                    $user_id     = $params['user_id'];
                    $student_id  = $params['student_id'];
                    $first_entry = array(
                        'user_type'  => "student",
                        'student_id' => $student_id,
                    );
                    $insert_data = array('user_type' => strtolower($user_type), 'create_student_id' => null);

                    if ($user_type == "Student") {
                        $insert_data['student_id'] = $user_id;
                    } elseif ($user_type == "Staff") {
                        $insert_data['staff_id'] = $user_id;
                    }
                    $insert_message = array(
                        'message'            => 'you are now connected on chat',
                        'chat_user_id'       => 0,
                        'is_first'           => 1,
                        'chat_connection_id' => 0,
                    );

                    //===================
                    $new_user_record = $this->chatuser_model->addNewUserForStudent($first_entry, $insert_data, 'student', $student_id, $insert_message);
                    $json_record     = json_decode($new_user_record);

                    //==================

                    $new_user = $this->chatuser_model->getChatUserDetail($json_record->new_user_id);

                    $chat_user = $this->chatuser_model->getMyID($student_id, 'student');

                    $data['chat_user']  = $chat_user;
                    $chat_connection_id = $json_record->new_user_chat_connection_id;
                    $chat_to_user       = 0;
                    $user_last_chat     = $this->chatuser_model->getLastMessages($chat_connection_id);

                    $chat_connection = $this->chatuser_model->getChatConnectionByID($chat_connection_id);
                    if (!empty($chat_connection)) {
                        $chat_to_user       = $chat_connection->chat_user_one;
                        $chat_connection_id = $chat_connection->id;
                        if ($chat_connection->chat_user_one == $chat_user->id) {
                            $chat_to_user = $chat_connection->chat_user_two;
                        }
                    }

                    $array = array('status' => '1', 'error' => '', 'message' => $this->lang->line('success_message'), 'new_user' => $new_user, 'chat_connection_id' => $json_record->new_user_chat_connection_id, 'chat_records' => $chat_records, 'user_last_chat' => $user_last_chat);

                    json_output($response['status'], $array);
                }
            }

        }

    }

    public function liveclasses()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST      = json_decode(file_get_contents("php://input"), true);
                    $student_id = $this->input->post('student_id');
                    $result     = $this->student_model->get($student_id);

                    $class_id   = $result->class_id;
                    $section_id = $result->section_id;

                    $live_classes = $this->conference_model->getByStudentClassSection($class_id, $section_id);
                    if (!empty($live_classes)) {
                        foreach ($live_classes as $lc_key => $lc_value) {
                            $live_url                            = json_decode($lc_value->return_response);
                            $live_classes[$lc_key]->{'join_url'} = $live_url->join_url;
                            unset($lc_value->return_response);

                        }
                    }

                    $data["live_classes"] = $live_classes;

                    json_output($response['status'], $data);
                }
            }
        }
    }
    
    public function livehistory()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } else {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) {
                    $_POST = json_decode(file_get_contents("php://input"), true);

                    $insert_data = array(
                        'student_id'    => $this->input->post('student_id'),
                        'conference_id' => $this->input->post('conference_id'),
                    );
                    $this->conference_model->updatehistory($insert_data);
                    $array = array('status' => '1', 'msg' => 'Success');

                    json_output($response['status'], $array);
                }
            }
        }
    }

    ///custom code for multi class
    public function multiclassstudent()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') 
        {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } 
        else 
        {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) 
            {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) 
                {
                    $data = array();
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $student_id = $params['student_id'];
                    // $keyword = $params['keyword'];
                    $resp['student_multi_classes'] = $this->webservice_model->searchMultiClsSectionByStudent($student_id);
                    json_output($response['status'], $resp);
                }
            }
        }
    }

    public function searchMultiStudentByClassSection()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') 
        {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } 
        else 
        {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) 
            {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) 
                {
                    $data = array();
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $class_id = $params['class_id'];
                    $section_id = $params['section_id'];
                    $student_id =$params['student_id'];
                    $resp['multi_classes_students'] = $this->webservice_model->searchByClassSectionWithSession($class_id,$section_id,$student_id);
                    json_output($response['status'], $resp);
                }
            }
        }

    }

    public function getStudentSessionClasses()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if ($method != 'POST') 
        {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } 
        else 
        {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) 
            {
                $response = $this->auth_model->auth();
                if ($response['status'] == 200) 
                {
                    $data = array();
                    $params     = json_decode(file_get_contents('php://input'), true);
                    $student_id = $params['student_id'];
                    $role = $params['role'];

                    if($role == "student") 
                    {
                        $data['studentclasses'] = $this->webservice_model->searchMultiClsSectionByStudent($student_id);
                    }
                    elseif($role == "parent") 
                    {
                        $data['studentclasses'] = $this->webservice_model->getParentChilds($student_id);
                    }
                    json_output($response['status'], $data);
                }
            }
        }
        
       
    }

    ///custom code for multi class
}
