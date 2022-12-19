<?php

namespace App\Http\Controllers;

use App\Models\Cases;
use App\Models\CasesAttachments;
use App\Models\Comments;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Validator;

class CasesController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $userRole = auth()->user()->role;
            $loggedId = auth()->user()->id;
            $sorting = $request->sorting ?? config("code.sorting.desc");
            $page = $request->page ?? "1";
            $perpage = $request->perpage;
            $globalSearch = $request->search ?? "";
            $sColumn = $request->column ?? "id";
            $status = $request->status;
            $category = $request->cat_search;
            // searching & filter data.
            $advanceSearch = true;
            if ($advanceSearch) {
                if ($userRole == config("code.roles.worker")) {
                    $query = Cases::query()->where('user_id', '=', $loggedId);
                } elseif ($userRole == config("code.roles.manager") || $userRole == config("code.roles.admin") || $userRole == config("code.roles.employer")) {
                    $query = Cases::where('status', '!=', 'Draft');
                } elseif ($userRole == config("code.roles.insurer")) {
                    $query = Cases::where('status', '=', 'With insurer and advisors');
                } else {
                    //
                }

                if ($request->case_id) {
                    $query->where('id', '=', $request->case_id);
                }
                if ($request->case_title) {
                    $query->where("case_title", 'LIKE', '%' . $request->case_title . '%');
                }
                if ($request->description) {
                    $query->where("description", 'LIKE', '%' . $request->description . '%');
                }
                if ($status) {
                    $query->where('status', '=', $status);
                }

                if ($request->last_name || $request->first_name || $request->email || $request->full_name) {
                    $query->where('is_anonymous', '0');
                }

                if ($globalSearch) {
                    if (is_numeric($globalSearch)) {
                        $query->where('id', $globalSearch);
                    } else {
                        $columns = collect(\DB::getSchemaBuilder()->getColumnListing('cases'));
                        $columns = $columns->filter(function ($value, $key) {
                            return in_array($value, ['case_title', 'description']) === true;
                        });
                        $query->where(function ($q) use ($columns, $globalSearch) {
                            foreach ($columns as $column) {
                                $q->orWhere($column, 'LIKE', '%' . $globalSearch . '%');
                            }
                            return $q;
                        });
                    }
                }
                $query = $query
                    ->with('user')
                    ->with('casesattachments')
                    ->with('category')
                    ->with('victimisation');
                if ($request->first_name) {
                    $firstname = $request->first_name;
                    $query->whereHas('user', function ($query) use ($firstname) {
                        $query->where("first_name", 'LIKE', '%' . $firstname . '%');
                    });
                }
                if ($request->last_name) {
                    $lastname = $request->last_name;
                    $query->whereHas('user', function ($query) use ($lastname) {
                        $query->where("last_name", 'LIKE', '%' . $lastname . '%');
                    });
                }
                if ($request->email) {
                    $email = $request->email;
                    $query->whereHas('user', function ($query) use ($email) {
                        $query->where("email", 'LIKE', '%' . $email . '%');
                    });
                }

                if ($request->full_name) {
                    $full_name = $request->full_name;
                    $query->whereHas('user', function ($query) use ($full_name) {
                        $query->where(\DB::raw("concat(first_name, last_name)"), 'LIKE', '%' . $full_name . '%');
                        $query->orWhere(\DB::raw("concat(first_name,' ',last_name)"), 'LIKE', '%' . $full_name . '%');
                    });
                }
                if ($request->cat_search) {
                    $category = $request->cat_search;
                    $query->whereHas('category', function ($query) use ($category) {
                        $query->where('id', '=', $category);
                    });
                }

            } else {

                $sorting = $request->sorting ?? config("code.sorting.desc");
                $globalSearch = $request->search ?? "";
                $sColumn = $request->column ?? "id";
                $status = $request->status;
                $page = $request->page ?? "1";
                $perpage = $request->perpage;
                $category = $request->cat_search;
                $user_id = $request->user_id ?? "0";

                if ($userRole == config("code.roles.worker")) {
                    if ($request->cat_search) {
                        $query = Cases::where('user_id', '=', $loggedId);
                        $query = $query
                            ->with('category')
                            ->whereHas('category', function ($query) use ($category) {
                                $query->where('id', '=', $category);
                            })
                            ->with('casesattachments')
                            ->with('user')
                            ->with('victimisation');
                    } else {
                        $query = Cases::query()->with(['category', 'casesattachments', 'user', 'victimisation'])->where('user_id', '=', $loggedId);
                    }

                } elseif ($userRole == config("code.roles.manager") || $userRole == config("code.roles.admin") || $userRole == config("code.roles.employer") || $userRole == config("code.roles.insurer")) {
                    if ($request->cat_search) {
                        if ($user_id != "0") {
                            $query = Cases::where('status', '!=', 'Draft')->where('user_id', '=', $user_id)->where('is_anonymous', '0');
                        } else {
                            $query = Cases::where('status', '!=', 'Draft');
                        }

                        $query = $query
                            ->with('category')
                            ->whereHas('category', function ($query) use ($category) {
                                $query->where('id', '=', $category);
                            })
                            ->with('casesattachments')
                            ->with('user')
                            ->with('victimisation');
                    } else {
                        if ($user_id != "0") {
                            $query = Cases::query()->with(['category', 'casesattachments', 'user', 'victimisation'])->where('status', '!=', 'Draft')->where('user_id', '=', $user_id)->where('is_anonymous', '0');
                        } else {
                            $query = Cases::query()->with(['category', 'casesattachments', 'user', 'victimisation'])->where('status', '!=', 'Draft');
                        }
                    }
                } else {}

                if ($status) {
                    $columns = ['status'];
                    $query->where(function ($q) use ($columns, $status) {
                        foreach ($columns as $column) {
                            $q->orWhere($column, 'LIKE', '%' . $status . '%');
                        }
                        return $q;
                    });
                }
                if ($globalSearch) {
                    if (is_numeric($globalSearch)) {
                        // serach for only case-ID.
                        $query->where('id', $globalSearch);
                    } else {
                        $columns = collect(\DB::getSchemaBuilder()->getColumnListing('cases'));
                        $columns = $columns->filter(function ($value, $key) {
                            return in_array($value, ['case_title', 'description']) === true;
                        });
                        $query->where(function ($q) use ($columns, $globalSearch) {
                            foreach ($columns as $column) {
                                $q->orWhere($column, 'LIKE', '%' . $globalSearch . '%');
                            }
                            return $q;
                        });

                        // $userArray = []; // serach by user data.
                        // $findInUser = User::select('id')->where(\DB::raw("concat(first_name, last_name)"), 'LIKE', '%' . $globalSearch . '%')
                        //     ->orWhere('email', 'LIKE', '%' . $globalSearch . '%')
                        //     ->orWhere(\DB::raw("concat(first_name,' ',last_name)"), 'LIKE', '%' . $globalSearch . '%')
                        //     ->get();

                        // if (count($findInUser)) {
                        //     foreach ($findInUser as $key => $user) {
                        //         $chekUser = Cases::where('user_id', $user->id)->get();
                        //         if (count($chekUser) > 0) {
                        //             array_push($userArray, $user->id);
                        //         }
                        //     }
                        //     $columns = ['user_id'];
                        //     $query->orWhere(function ($q) use ($columns, $userArray) {
                        //         foreach ($columns as $column) {
                        //             $q->orWhereIn($column, $userArray);
                        //         }
                        //         return $q;
                        //     });
                        // }
                    }
                }
                $query->orderBy($sColumn, $sorting);
            }
            $query->orderBy($sColumn, $sorting);
            $total = $query->count();

            if ($request->perpage) {
                $offset = ($page - 1) * $perpage;
                // print_r($query->toSql());exit;
                $cases = $query->offset($offset)->limit($perpage)->get();
            } else {
                $cases = $query->get();
            }

            $cases = [
                'cases' => $cases,
                'total' => $total,
                'page' => $page,
                'lastpage' => $request->perpage ? ceil($total / $perpage) : "",
            ];
            return $this->_api_response(config("code.success.caselist"), ["success" => true, "data" => $cases]);
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.caselist"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            if (auth()->user()->role == config("code.roles.worker")) {
                $validator = Validator::make($request->all(), [
                    'case_title' => 'required|string|max:55',
                    'category_id' => 'required',
                    // 'duration_end_date' => 'required',
                    // 'duration_start_date' => 'required',
                    'description' => 'required',
                    'by_whom' => 'required',
                    'involved' => 'required',
                    'no_observed' => 'required',
                    'find_from' => 'required',
                    'status' => 'required',
                    'file.*' => 'mimes:jpg,jpeg,png,bmp,mp3,mp4,pdf,csv,txt,doc,docx,mov,odt,m4a,heic|max:2560000',
                ], [
                    'file.*.mimes' => 'Only jpg,jpeg,png,bmp,mp3,mp4,pdf,csv,txt,doc,docx,mov,odt,m4a,heic mimes are allowed',
                    'file.*.max' => 'Sorry! Maximum allowed size for an image is 250MB',
                ]

                );

                if ($validator->fails()) {
                    return $this->_error_api_response(config("code.errors.validation"), ["message" => $validator->errors()->first()], "");
                }
                $case = Cases::create([
                    'case_id' => Str::uuid(),
                    'case_title' => $request->case_title,
                    'category_id' => $request->category_id,
                    'description' => $request->description,
                    'by_whom' => $request->by_whom,
                    'involved' => $request->involved,
                    'no_observed' => $request->no_observed,
                    'find_from' => $request->find_from,
                    'status' => $request->status,
                    'user_id' => auth()->user()->id,
                    'duration_start_date' => $request->duration_start_date ?? null,
                    'duration_end_date' => $request->duration_end_date ?? null,
                    'is_anonymous' => $request->is_anonymous,
                ]);

                if ($file = $request->file('file')) {
                    foreach ($file as $key => $file) {
                        $path = $file->store('public');
                        $name = $file->getClientOriginalName();

                        $encryptedImageName = explode("/", $path);
                        //store your file into directory and db
                        $mime = $_FILES['file']['type'];
                        $mime = $mime[$key];

                        $save = new CasesAttachments();
                        $save->cases_id = $case->id;
                        $save->user_id = auth()->user()->id;
                        $save->document = $name;
                        $save->document_path = $path;
                        $save->document_type = $mime;
                        $save->dispay_path = env('IMAGE_PATH') . $encryptedImageName[1];
                        $save->save();
                    }
                }

                // sending mail to all except worker/insurer/manager/admin
                if ($request->status != "Draft") {
                    $users = User::select('email')->where('role', '=', 'Employer')->where('is_active', 1)->get();
                    // $users = User::select('email')->where('role', '!=', 'Worker')->where('is_active', 1)->get();
                    $subject = config("code.emailsubject.newcase");
                    $logged = auth()->user();

                    if (count($users) > 0 && $request->case_title && $request->description) {
                        $viewfile = 'emails.worker_details_employer';
                        $data['case_title'] = $request->case_title;
                        $data['case_id'] = $case->id;
                        $data['description'] = strip_tags($request->description);
                        $data['added_by'] = ($case->is_anonymous) ? "Anonymous" : $logged->first_name . ' ' . $logged->last_name . '(' . $logged->email . ')';
                        foreach ($users as $row) {
                            sendmail($data, $row, $viewfile, $subject);
                        }
                        //send mail to worker
                        $viewfile = 'emails.worker_details';
                        $workerEmail['email'] = $logged->email;
                        sendmail($data, $workerEmail, $viewfile, $subject);
                    }
                }
                return $this->_api_response(config("code.success.cases"), ["success" => true, 'message' => config("code.messages.cases"), "id" => $case->id]);
            } else {
                return $this->_error_api_response(config("code.errors.cases"), ["message" => config("code.messages.cases-not-authorised")], "");
            }
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.cases"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userRole = auth()->user()->role;
        if ($userRole == config("code.roles.worker")) {
            $checkForAuth = Cases::select('user_id')->where('id', $id)->first();
            if ($checkForAuth) {
                if ($checkForAuth->user_id != auth()->user()->id) {
                    return $this->_error_api_response(config("code.errors.caselist"), ["message" => config("code.messages.notforcase")], "");
                }
            }
        }

        $cases = Cases::query()->with(['category', 'casesattachments', 'comments', 'comments.user', 'comments.commentsAttachments', 'user', 'victimisation'])->where('id', '=', $id)->get();
        return $this->_api_response(config("code.success.caselist"), ["success" => true, "data" => $cases]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            if (auth()->user()->role == config("code.roles.worker")) {
                $validator = Validator::make($request->all(), [
                    'case_title' => 'required|string|max:55',
                    'category_id' => 'required',
                    // 'duration_end_date' => 'required',
                    // 'duration_start_date' => 'required',
                    'description' => 'required',
                    'by_whom' => 'required',
                    'involved' => 'required',
                    'no_observed' => 'required',
                    'find_from' => 'required',
                    'status' => 'required',
                    'file.*' => 'mimes:jpg,jpeg,png,bmp,mp3,mp4,pdf,csv,txt,doc,docx,mov,odt,m4a,heic|max:2560000',
                ], [
                    'file.*.mimes' => 'Only jpg,jpeg,png,bmp,mp3,mp4,pdf,csv,txt,doc,docx,mov,odt,m4a,heic mimes are allowed',
                    'file.*.max' => 'Sorry! Maximum allowed size for an image is 250MB',
                ]);

                if ($validator->fails()) {
                    return $this->_error_api_response(config("code.errors.validation"), ["message" => $validator->errors()->first()], "");
                }
                $cases = Cases::find($id);
                if ($cases) {
                    $cases->case_title = $request->case_title;
                    $cases->category_id = $request->category_id;
                    $cases->description = $request->description;
                    $cases->by_whom = $request->by_whom;
                    $cases->involved = $request->involved;
                    $cases->no_observed = $request->no_observed;
                    $cases->find_from = $request->find_from;
                    $cases->status = $request->status;
                    $cases->duration_start_date = $request->duration_start_date ?? null;
                    $cases->duration_end_date = $request->duration_end_date ?? null;
                    $cases->is_anonymous = $request->is_anonymous;
                    $cases->save();

                    if ($file = $request->file('file')) {
                        foreach ($file as $key => $file) {
                            $path = $file->store('public');
                            $name = $file->getClientOriginalName();

                            $encryptedImageName = explode("/", $path);
                            //store your file into directory and db
                            $mime = $_FILES['file']['type'];
                            $mime = $mime[$key];

                            $save = new CasesAttachments();
                            $save->cases_id = $cases->id;
                            $save->user_id = auth()->user()->id;
                            $save->document = $name;
                            $save->document_path = $path;
                            $save->document_type = $mime;
                            $save->dispay_path = env('IMAGE_PATH') . $encryptedImageName[1];
                            $save->save();
                        }
                    }
                }
                // sending mail to all except worker
                if ($request->status == "Open" || $request->status == "open") {
                    $users = User::select('email')->where('role', '=', 'Employer')->where('is_active', 1)->get();
                    // $users = User::select('email')->where('role', '!=', 'Worker')->where('is_active', 1)->get();
                    $viewfile = 'emails.worker_details';
                    $subject = config("code.emailsubject.newcase");
                    $logged = auth()->user();

                    if (count($users) > 0 && $request->case_title && $request->description) {
                        $data['case_title'] = $request->case_title;
                        $data['case_id'] = $cases->id;
                        $data['description'] = strip_tags($request->description);
                        $data['added_by'] = ($cases->is_anonymous) ? "Anonymous" : $logged->first_name . ' ' . $logged->last_name . '(' . $logged->email . ')';
                        foreach ($users as $row) {
                            sendmail($data, $row, $viewfile, $subject);
                        }
                    }
                }
                return $this->_api_response(config("code.success.cases"), ["success" => true, 'message' => config("code.messages.cases")]);
            } else {
                return $this->_error_api_response(config("code.errors.cases"), ["message" => config("code.messages.cases-not-authorised")], "");
            }
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.cases"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteAttachment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->_error_api_response(config("code.errors.categorystatus"), ["message" => $validator->errors()->first()], "");
            }
            $attachment = CasesAttachments::where('id', $request->id)->delete();
            return $this->_api_response(config("code.success.categorystatus"), ["success" => true]);
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.categorystatus"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }

    /**
     * Change cases status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changeCaseStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'case_id' => 'required',
                'status' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->_error_api_response(config("code.errors.categorystatus"), ["message" => $validator->errors()->first()], "");
            }
            $case = Cases::find($request->case_id);
            if (!$case) {
                return $this->_error_api_response(config("code.errors.categorystatus"), ["message" => config("code.messages.errormessage")], "");
            }
            $case->status = $request->status;
            $case->save();

            // sending mail to worker
            $user = User::select('email')->where('id', $case->user_id)->where('is_active', 1)->first();
            if ($user) {
                $data['case_title'] = $case->case_title;
                $data['description'] = $case->description;
                $data['status'] = $case->status;
                $data['case_id'] = $case->id;
                $data['created_at'] = $case->created_at;
                $detail['email'] = $user->email;
                $viewfile = 'emails.case_status';
                $subject = config("code.emailsubject.casestatus");

                if ($user && $data) {
                    sendmail($data, $detail, $viewfile, $subject);
                }
            }
            return $this->_api_response(config("code.success.casestatus"), ["success" => true, "message" => config("code.messages.casestatus")]);
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.casestatus"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }

    /**
     * resolvedByEmployer function
     *
     * @return void
     */
    public function resolvedByEmployer(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'case_id' => 'required',
                'is_resolved' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->_error_api_response(config("code.errors.categorystatus"), ["message" => $validator->errors()->first()], "");
            }
            $case = Cases::find($request->case_id);
            if (!$case) {
                return $this->_error_api_response(config("code.errors.categorystatus"), ["message" => config("code.messages.errormessage")], "");
            }
            if ($request->is_resolved == 2) {
                $case->status = 'Closed';
            }
            $case->is_resolved = $request->is_resolved;
            $case->save();

            // sending mail
            $data['case_title'] = $case->case_title;
            $data['case_id'] = $case->id;
            $data['description'] = $case->description;
            $data['created_at'] = $case->created_at;
            if ($request->is_resolved == 2) {
                // sending mail to employer
                $employers = User::select('email')->where('role', '=', 'Employer')->where('is_active', 1)->get();
                // $employers = User::select('email')->where('role', '!=', 'Worker')->where('is_active', 1)->get();
                $viewfileEmp = 'emails.case_closed_employer';
                $subjectEmp = config("code.emailsubject.caseclosed");

                if (count($employers) > 0 && $data) {
                    foreach ($employers as $row) {
                        $detail['email'] = $row->email;
                        sendmail($data, $detail, $viewfileEmp, $subjectEmp);
                    }
                }
                $viewfileEmp = 'emails.case_closed';
                $details['email'] = auth()->user()->email;
                sendmail($data, $details, $viewfileEmp, $subjectEmp);
                // insert message
                $comments = Comments::create([
                    'cases_id' => $case->id,
                    'user_id' => auth()->user()->id,
                    'description' => "<p style='color:blue;'>This case has now been marked as closed.</p>",
                    'is_system' => "1",
                ]);
            } else {
                // sending mail to worker
                $user = User::select('email')->where('id', $case->user_id)->where('is_active', 1)->first();
                if ($user) {
                    $detail['email'] = $user->email;
                    $viewfile = 'emails.case_resolved';
                    $subject = config("code.emailsubject.caseresolved");

                    if ($user && $detail) {
                        sendmail($data, $detail, $viewfile, $subject);
                    }
                }
                // insert message
                $comments = Comments::create([
                    'cases_id' => $case->id,
                    'user_id' => auth()->user()->id,
                    'description' => "<p style='color:blue;'>The employer has marked this case as resolved.</p>",
                    'is_system' => "1",
                ]);
            }
            return $this->_api_response(config("code.success.caseresolved"), ["success" => true, "message" => config("code.messages.caseresolved")]);
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.caseresolved"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }

    /**
     * logVictimisations function
     *
     * @return void
     */
    public function logVictimisations(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cases_id' => 'required',
                'victimisations_id' => 'required',
                'victimisations_status' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->_error_api_response(config("code.errors.vc-categorystatus"), ["message" => $validator->errors()->first()], "");
            }
            $case = Cases::find($request->cases_id);
            if (!$case) {
                return $this->_error_api_response(config("code.errors.vc-categorystatus"), ["message" => config("code.messages.errormessage")], "");
            }
            $userRole = auth()->user()->role;
            $successMsg = config("code.messages.casesrvc");

            if ($userRole == config("code.roles.worker")) {
                // if ($userRole == config("code.roles.worker") && $request->victimisations_status == 'REQUESTED') {
                $case->victimisations_id = $request->victimisations_id;
                // $case->victimisations_status = $request->victimisations_status;
                $case->victimisations_status = 'ACCEPTED';
                $case->status = "With insurer and advisors";
                $case->victimisations_description = $request->victimisations_description;
                $case->is_anonymous = '0';

                // sending mail to all except worker
                // $users = User::select('email')->where('role', '!=', 'Worker')->where('is_active', 1)->get();
                $users = User::select('email', 'role')->whereIn('role', ['Employer', 'Insurer'])->where('is_active', 1)->get();
                $logged = auth()->user();
                $data['case_title'] = $case->case_title;
                $data['case_id'] = trim($case->id);
                $data['description'] = $case->description;
                $data['victimisations_description'] = $case->victimisations_description ?? "";
                $data['created_at'] = $case->created_at;
                $data['added_by'] = ($case->is_anonymous) ? "Anonymous" : $logged->first_name . ' ' . $logged->last_name . '(' . $logged->email . ')';
                $data['case_status'] = $case->status;
                $data['email'] = $logged->email;

                $subject = config("code.emailsubject.logsvictimisation");

                if (count($users) > 0 && $data) {
                    foreach ($users as $row) {
                        if ($row->role == config("code.roles.employer")) {
                            $viewfile = 'emails.logs_victimisation_employer';
                        } else {
                            $viewfile = 'emails.logs_victimisation';
                        }
                        $detail['email'] = $row->email;
                        sendmail($data, $detail, $viewfile, $subject);
                    }
                }
                $successMsg .= " " . Str::lower($request->victimisations_status);

                // insert message
                $comments = Comments::create([
                    'cases_id' => $case->id,
                    'user_id' => auth()->user()->id,
                    'description' => "<p style='color:blue;'>A report of victimisation has been made</p>",
                    'is_system' => "1",
                ]);

            } elseif (($userRole == config("code.roles.manager") || $userRole == config("code.roles.admin") || $userRole == config("code.roles.employer")) && ($request->victimisations_status == 'ACCEPTED')) {
                $case->victimisations_status = $request->victimisations_status;
                if ($request->victimisations_status == 'ACCEPTED') {
                    $case->status = "With insurer and advisors";
                }
                // sending mail to worker
                $user = User::select('email')->where('id', $case->user_id)->where('is_active', 1)->first();
                if ($user) {
                    $data['case_title'] = $case->case_title;
                    $data['case_id'] = trim($case->id);
                    $data['case_status'] = trim($case->status);
                    $data['description'] = $case->description;
                    $data['status'] = $request->victimisations_status;
                    $data['created_at'] = $case->created_at;
                    $detail['email'] = $user->email;
                    $viewfile = 'emails.victimisation_status';
                    $subject = config("code.emailsubject.victimisation_status");

                    if ($user && $data) {
                        sendmail($data, $detail, $viewfile, $subject);
                    }
                }
                $successMsg .= " request " . Str::lower($request->victimisations_status);
            } else {
                return $this->_error_api_response(config("code.errors.vc-categorystatus"), ["message" => config("code.messages.vc-not-authorised")], "");
            }
            $case->save();

            return $this->_api_response(config("code.success.casevc"), ["success" => true, "message" => "Victimisation has been reported successfully."]);
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.casevc"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }

    /**
     * secureMedia function
     *
     * @return download media
     */
    public function secureMedia($filename)
    {
        if (auth()->user()) {
            $downloadPath = config('filesystems.disks.media.root') . "/" . $filename;
            return response()->file($downloadPath);
            // return $this->_api_response(config("code.success.casevc"), ["success" => true, "image" => response()->file($downloadPath)]);
        } else {
            return $this->_error_api_response(config("code.errors.securemedia"), ["message" => config("code.messages.notforviewfile")], "");
        }
    }
    /**
     * requestReadOnly function
     *
     * @param [type] $filename
     * @return void
     */
    public function requestReadOnly(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cases_id' => 'required',
                'is_read_request' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->_error_api_response(config("code.errors.vc-categorystatus"), ["message" => $validator->errors()->first()], "");
            }
            $case = Cases::find($request->cases_id);
            if (!$case) {
                return $this->_error_api_response(config("code.errors.vc-categorystatus"), ["message" => config("code.messages.errormessage")], "");
            }
            $userRole = auth()->user()->role;
            if ($userRole != config("code.roles.insurer") && $request->is_read_request == '1') {

                return $this->_error_api_response(config("code.errors.vc-categorystatus"), ["message" => config("code.messages.errormessage"), "error" => "asdasd"], "");
            }

            $case->is_read_request = $request->is_read_request;
            if ($request->is_read_request == '1') { //request
                // sending mail to all except worker ,Insurer
                $users = User::select('email')->whereIn('role', ['Employer'])->where('is_active', 1)->get();
                // $users = User::select('email')->whereNotIn('role', ['Worker', 'Insurer'])->where('is_active', 1)->get();
                $logged = auth()->user();
                $data['case_title'] = $case->case_title;
                $data['case_id'] = trim($case->id);
                $data['description'] = $case->description;
                $data['victimisations_description'] = $case->victimisations_description ?? "";
                $data['created_at'] = $case->created_at;
                $data['added_by'] = ($case->is_anonymous) ? "Anonymous" : $case->user->first_name . ' ' . $case->user->last_name . '(' . $case->user->email . ')';
                $data['case_status'] = $case->status;
                $data['header_msg'] = "READ-ONLY ACCESS REQUESTED";
                $data['msg'] = "The insurer has requested read-only access to the case below.";
                $data['is_button'] = "";
                $viewfile = 'emails.request_access';
                $subject = "Read-only access to case requested";

                if (count($users) > 0 && $data) {
                    foreach ($users as $row) {
                        $detail['email'] = $row->email;
                        sendmail($data, $detail, $viewfile, $subject);
                    }
                }
                $successMsg = "You have requested read-only access to this case";
                // insert message
                $comments = Comments::create([
                    'cases_id' => $case->id,
                    'user_id' => auth()->user()->id,
                    'description' => "<p style='color:blue;'>Insurer has requested read-only access to the case</p>",
                    'is_system' => "1",
                ]);
            } elseif ($request->is_read_request == '2' || $request->is_read_request == '3') { //accept - declined
                // sending mail to insurer
                $users = User::select('email')->where('role', 'Insurer')->where('is_active', 1)->get();
                $data['case_title'] = $case->case_title;
                $data['case_id'] = trim($case->id);
                $data['description'] = $case->description;
                $data['victimisations_description'] = $case->victimisations_description ?? "";
                $data['created_at'] = $case->created_at;
                $data['added_by'] = ($case->is_anonymous) ? "Anonymous" : $case->user->first_name . ' ' . $case->user->last_name . '(' . $case->user->email . ')';
                $data['case_status'] = $case->status;
                $data['is_button'] = "Yes";
                $subject = config("code.emailsubject.requestAccess");
                if ($request->is_read_request == '2') {
                    $data['header_msg'] = "READ-ONLY ACCESS: GRANTED";
                    $data['preview'] = "You have been granted read-only access to this case.";
                    $data['msg'] = "[COMPANY NAME] has reviewed your request for read-only access to the below case and accepted your request. You now have full access to the case on PRΩTEC®";
                    $data['next_text'] = "You can now log into your PRΩTEC® account to view the full details of the original case raised by the worker.";
                    $subject = config("code.emailsubject.requestGranted");
                    $comments = Comments::create([
                        'cases_id' => $case->id,
                        'user_id' => auth()->user()->id,
                        'description' => "<p style='color:blue;'>Insurer has been granted read-only access to the case</p>",
                        'is_system' => "1",
                    ]);
                    $successMsg = "Insurer has been granted read-only access to the case";
                }

                if ($request->is_read_request == '3') { // declined
                    $data['header_msg'] = "READ-ONLY ACCESS: DENIED";
                    $data['preview'] = "You have been denied read-only access to this case.";
                    $data['msg'] = "[COMPANY NAME] has reviewed your request for read-only access to the below case and access has been denied.";
                    $data['next_text'] = "Please contact [COMPANY NAME] directly.";
                    $data['is_button'] = "";
                    $subject = config("code.emailsubject.requestDenied");
                    $comments = Comments::create([
                        'cases_id' => $case->id,
                        'user_id' => auth()->user()->id,
                        'description' => "<p style='color:blue;'>The insurer has been denied read-only access to this case.</p>",
                        'is_system' => "1",
                    ]);
                    $successMsg = "The insurer has been denied read-only access to this case.";
                }
                $viewfile = 'emails.request_access_granted_denied';

                if (count($users) > 0 && $data) {
                    foreach ($users as $row) {
                        $detail['email'] = $row->email;
                        sendmail($data, $detail, $viewfile, $subject);
                    }
                }

            } else {
                //
            }
            $case->save();

            return $this->_api_response(config("code.success.casevc"), ["success" => true, "message" => $successMsg]);
        } catch (\Exception$e) {
            return $this->_error_api_response(config("code.errors.casevc"), ["message" => config("code.messages.errormessage"), "error" => $e->getMessage()], "");
        }
    }
}
