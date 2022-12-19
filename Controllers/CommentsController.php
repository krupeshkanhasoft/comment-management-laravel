<?php

namespace App\Http\Controllers;

use App\Models\Cases;
use App\Models\Comments;
use App\Models\CommentsAttachments;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;

class CommentsController extends ApiController
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cases_id' => 'required',
                'description' => 'required',
                'file.*' => 'mimes:jpg,jpeg,png,bmp,mp3,mp4,pdf,csv,txt,doc,docx,mov,odt,m4a,heic|max:2560000',
            ], [
                'file.*.mimes' => 'Only jpg,jpeg,png,bmp,mp3,mp4,pdf,csv,txt,doc,docx,mov,odt,m4a,heic mimes are allowed',
                'file.*.max' => 'Sorry! Maximum allowed size for an image is 250MB',
            ]
            );

            if ($validator->fails()) {
                return $this->_error_api_response(config("code.errors.validation"), ["message" => $validator->errors()->first()], "");
            }
            $comments = Comments::create([
                'cases_id' => $request->cases_id,
                'user_id' => auth()->user()->id,
                'description' => $request->description,
            ]);
            if ($file = $request->file('file')) {
                foreach ($file as $key => $file) {
                    $path = $file->store('public');
                    $name = $file->getClientOriginalName();

                    $encryptedImageName = explode("/", $path);
                    //store your file into directory and db
                    $mime = $_FILES['file']['type'];
                    $mime = $mime[$key];
                    $save = new CommentsAttachments();
                    $save->cases_id = $request->cases_id;
                    $save->user_id = auth()->user()->id;
                    $save->comments_id = $comments->id;
                    $save->document = $name;
                    $save->document_path = $path;
                    $save->document_type = $mime;
                    $save->dispay_path = env('IMAGE_PATH') . $encryptedImageName[1];
                    $save->save();
                }
            }

            // sending mail to all except worker
            $users = User::select('email')->where('role', '=', 'Employer')->where('is_active', 1)->get();
            $viewfile = 'emails.comment_details_employer';
            $subject = config("code.emailsubject.newcomment");
            $logged = auth()->user();
            $data['description'] = ($request->description) ? strip_tags($request->description) : '';
            $data['description'] = trim($data['description']);
            $findTitle = Cases::select('id', 'case_title', 'is_anonymous')->where('id', $request->cases_id)->first();

            if (count($users) > 0) {
                foreach ($users as $row) {
                    if ($findTitle) {
                        $data['case_title'] = $findTitle->case_title;
                        $data['case_id'] = $findTitle->id;
                        if ($logged->role == config("code.roles.worker")) {
                            $data['added_by'] = ($findTitle->is_anonymous) ? "Anonymous" : $logged->first_name . ' ' . $logged->last_name . '(' . $logged->email . ')';
                        } else {
                            $data['added_by'] = $logged->first_name . ' ' . $logged->last_name . '(' . $logged->email . ')';
                        }
                        sendmail($data, $row, $viewfile, $subject);
                    }
                }
            }

            // send mail to worker
            $worker = Cases::select('users.email')
                ->leftJoin('users', 'users.id', '=', 'cases.user_id')
                ->where('cases.id', $request->cases_id)
                ->where('users.is_active', 1)
                ->first();
            if ($worker) {
                if ($worker->email != $logged->email) {
                    if ($findTitle) {
                        $viewfile = 'emails.comment_details';
                        $data['case_title'] = $findTitle->case_title;
                        $detail['email'] = $worker->email;
                        $data['added_by'] = $logged->first_name . ' ' . $logged->last_name . '(' . $logged->email . ')';
                        sendmail($data, $detail, $viewfile, $subject);
                    }
                }
            }

            return $this->_api_response(config("code.success.comment"), ["success" => true, 'message' => config("code.messages.comment")]);
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.comment"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }
}
