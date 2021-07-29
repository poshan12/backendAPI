<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('auth_model');
        $this->load->library('mailer');
        $this->load->library(array('customlib', 'enc_lib'));
    }

    public function login()
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
                $params   = json_decode(file_get_contents('php://input'), true);
                // $company_name = $params['company_name'];
                // $company_gst_no  = $params['company_gst_no'];
                $company_email  = $params['company_email'];
                $password = $params['company_password'];
                $response = $this->auth_model->chklogin($company_email, $password);
                json_output($response['status'], $response);

            }
        }
    }

    public function singup()
    {
        $method = $this->input->server('REQUEST_METHOD');
        if($method != 'POST') 
        {
            json_output(400, array('status' => 400, 'message' => 'Bad request.'));
        } 
        else 
        {
            $check_auth_client = $this->auth_model->check_auth_client();
            if ($check_auth_client == true) 
            {
                $params   = json_decode(file_get_contents('php://input'), true);
                $company_name = $params['company_name'];
                $company_gst_no  = $params['company_gst_no'];
                $company_email  = $params['company_email'];
                $rpass=rand(1,999999);
                $password =$rpass;
                $data=array();
                $data=array(
                    'company_name'=>$company_name,
                    'company_gst_no'=>$company_gst_no,
                    'company_email'=>$company_email,
                    'company_password'=>$password
                );
                $response = $this->auth_model->singup($data);
                $insertID=$response['id'];             

                $name=$response['company_name'];
                $email=$response['company_email'];
                $body       = $this->loginDetailMailBody($name,$password,$email);
                $body_array = json_decode($body);
                if(!empty($insertID))
                {
                        $result = $this->mailer->send_mail($email, $body_array->subject, $body_array->body);
                        if ($result) 
                        {
                            $respStatus = 200;
                            $resp       = array('status' => 200, 'message' => "check your mail for login details");
                            json_output($respStatus, $resp);
                        } 
                        else 
                        {
                            $respStatus = 200;
                            $resp       = array('status' => 200, 'message' => "Sending of message failed, Please contact to Admin.");
                            json_output($respStatus, $resp);
                        }
                
                    // json_output($response['status'], $response);

                }
                else
                {
                    json_output($response['status'], $response);
                }
                
               

            }
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
                <br/>' . $name;

        //======================
        return json_encode(array('subject' => $subject, 'body' => $body));
    }

    public function loginDetailMailBody($name,$password,$email)
    {
        $subject = "Password details";
        $body    = 'Dear ' . $name . ',
                <br/>This is the user name '.$email.'  and password '.$password.' for your account. please use this for login into site.';
        $body .= '<br/><hr/>please use this for login';
        $body .= '<br/><br/>Regards,
                <br/>' . $name;

        return json_encode(array('subject' => $subject, 'body' => $body));
    }
}
