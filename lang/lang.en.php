<?php
/* ─────────────────────────────────────────────
   InfoDEQB — Language: English
   ───────────────────────────────────────────── */
$lang = [];

// ── Common buttons & actions ──────────────────
$lang['SAVE']           = 'Save';
$lang['SAVE_CHANGES']   = 'Save changes';
$lang['CANCEL']         = 'Cancel';
$lang['EDIT']           = 'Edit';
$lang['DELETE']         = 'Delete';
$lang['REMOVE']         = 'Remove';
$lang['ADD']            = 'Add';
$lang['CREATE']         = 'Create';
$lang['SUBMIT']         = 'Submit';
$lang['SEARCH']         = 'Search';
$lang['FILTER']         = 'Filter';
$lang['CLEAR']          = 'Clear';
$lang['BACK']           = 'Back';
$lang['CLOSE']          = 'Close';
$lang['PRINT']          = 'Print';
$lang['EXPORT']         = 'Export';
$lang['IMPORT']         = 'Import';
$lang['DOWNLOAD']       = 'Download';
$lang['UPLOAD']         = 'Upload file';
$lang['VIEW']           = 'View';
$lang['DETAILS']        = 'Details';
$lang['CONFIRM']        = 'Confirm';
$lang['APPLY']          = 'Apply';
$lang['NEXT']           = 'Next';
$lang['PREVIOUS']       = 'Previous';
$lang['YES']            = 'Yes';
$lang['NO']             = 'No';
$lang['ALL']            = 'All';

// ── Common fields ─────────────────────────────
$lang['NAME']           = 'Name';
$lang['FULL_NAME']      = 'Full name';
$lang['EMAIL']          = 'FEUP Email';
$lang['ALT_EMAIL']      = 'Alternative email';
$lang['PHONE']          = 'Phone';
$lang['CODE']           = 'Code';
$lang['FEUP_CODE']      = 'FEUP Code';
$lang['DATE']           = 'Date';
$lang['BEGIN_DATE']     = 'Start date';
$lang['END_DATE']       = 'End date';
$lang['STATUS']         = 'Status';
$lang['ACTIONS']        = 'Actions';
$lang['DESCRIPTION']    = 'Description';
$lang['OBSERVATIONS']   = 'Observations';
$lang['YEAR']           = 'Year';
$lang['QUANTITY']       = 'Quantity';
$lang['TOTAL']          = 'Total';
$lang['TYPE']           = 'Type';
$lang['CATEGORY']       = 'Category';
$lang['RESPONSIBLE']    = 'Responsible';
$lang['COURSE']         = 'Course';
$lang['SIGNATURE']      = 'Signature';
$lang['DATA']           = 'Data';

// ── Feedback messages ─────────────────────────
$lang['SUCCESS_SAVED']      = 'Saved successfully.';
$lang['SUCCESS_DELETED']    = 'Deleted successfully.';
$lang['SUCCESS_ADDED']      = 'Added successfully.';
$lang['SUCCESS_UPDATED']    = 'Updated successfully.';
$lang['SUCCESS_SUBMITTED']  = 'Submitted successfully.';
$lang['ERROR_SAVE']         = 'Error saving. Please try again.';
$lang['ERROR_DELETE']       = 'Error deleting.';
$lang['ERROR_GENERIC']      = 'An unexpected error occurred.';
$lang['ERROR_PERMISSION']   = 'You do not have permission to perform this action.';
$lang['ERROR_NOT_FOUND']    = 'Record not found.';
$lang['CONFIRM_DELETE']     = 'Are you sure you want to delete this record?';
$lang['IRREVERSIBLE']       = 'This action cannot be undone.';
$lang['NO_RECORDS']         = 'No records to display.';
$lang['NO_RESULTS']         = 'No results for the current filters.';
$lang['LOADING']            = 'Loading…';
$lang['SAVING']             = 'Saving…';
$lang['REQUIRED_FIELD']     = 'Required field';
$lang['N_RECORDS']          = '%d record(s)';

// ── Navigation ────────────────────────────────
$lang['NAV_HOME']           = 'Home';
$lang['NAV_STAFF']          = 'Staff';
$lang['NAV_MY_RECORD']      = 'Staff registration';
$lang['NAV_STAFF_LIST']     = 'Active staff list';
$lang['NAV_STAFF_ADMIN']    = 'Queries & management';
$lang['NAV_RESOURCES']      = 'Resources';
$lang['NAV_EQUIPMENT']      = 'Equipment';
$lang['NAV_REAGENTS']       = 'Reagents';
$lang['NAV_BOOKING']        = 'Resource booking';
$lang['NAV_WATER_QUALITY']  = 'Water quality';
$lang['NAV_WATER_ADMIN']    = 'Ultrapure water';
$lang['NAV_GASES']          = 'Special gases';
$lang['NAV_TEACHING']       = 'Teaching';
$lang['NAV_EXAMS']          = 'Exam archive';
$lang['NAV_MOBILITY']       = 'Mobility EQ';
$lang['NAV_DEPT']           = 'Department';
$lang['NAV_AREAS']          = 'Scientific areas';
$lang['NAV_SERVDOC']        = 'Teaching preference';
$lang['NAV_SPACES']         = 'Research spaces';
$lang['NAV_DSD']            = 'Teaching service';
$lang['NAV_LOGOUT']         = 'Sign out';
$lang['NAV_ADMIN']          = 'Admin';

// ── Dashboard ─────────────────────────────────
$lang['DATE_FMT_LONG']      = '%1$s, %3$s %2$d, %4$d';
$lang['DAYS_OF_WEEK']       = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$lang['MONTHS']             = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$lang['DASH_HELLO']         = 'Hello, %s!';
$lang['DASH_WELCOME']       = 'welcome';
$lang['DASH_DEPT']          = 'Department of Chemical and Biological Engineering';
$lang['DASH_ADMIN_BADGE']   = 'Administrator';
$lang['DASH_MANAGER_BADGE'] = 'Manager';
$lang['DASH_MODULES']       = 'Modules';
$lang['DASH_ALERTS']        = 'Alerts';
$lang['DASH_TOOLS']         = 'Tools';
$lang['DASH_ADMIN_SEC']     = 'Administration';
$lang['DASH_NEW_RECORDS']   = 'New records';
$lang['DASH_PENDING']       = 'Pending';
$lang['DASH_EXPIRING']      = 'Expiring (30 days)';
$lang['DASH_ACTIVE']        = 'Active records';
$lang['DASH_NO_EXPIRING']   = 'No contracts expiring';
$lang['DASH_SEE_ALL']       = 'See all';
$lang['DASH_MY_RECORD']     = 'Staff registration';
$lang['DASH_MY_FORMS']      = 'My forms';
$lang['DASH_QUICKLINKS']    = 'Quick access';
$lang['DASH_BACKUP']        = 'Database backup';
$lang['DASH_BACKUP_DESC']   = 'Export data to SQL file';
$lang['DASH_SECTION_ADMINS']      = 'Administration';
$lang['DASH_SECTION_ADMINS_DESC'] = 'Module admins, forms and configuration';
$lang['DASH_MANAGE']        = 'Manage';

// ── Record status ─────────────────────────────
$lang['STATUS_ACTIVE']      = 'Active';
$lang['STATUS_INACTIVE']    = 'Inactive';
$lang['STATUS_PENDING']     = 'Pending';
$lang['STATUS_NEW']         = 'New';
$lang['STATUS_APPROVED']    = 'Approved';
$lang['STATUS_REJECTED']    = 'Rejected';
$lang['STATUS_EXPIRED']     = 'Expired';
$lang['STATUS_SUBMITTED']   = 'Submitted';
$lang['STATUS_NOT_SUBMITTED'] = 'Not submitted';

// ── DataTables / pagination ───────────────────
$lang['DT_SEARCH']          = 'Search:';
$lang['DT_SHOW']            = 'Show _MENU_ records';
$lang['DT_INFO']            = 'Showing _START_ to _END_ of _TOTAL_';
$lang['DT_INFO_EMPTY']      = 'No records';
$lang['DT_INFO_FILTERED']   = '(filtered from _MAX_ total)';
$lang['DT_ZERO']            = 'No matching records found';
$lang['DT_EMPTY']           = 'No data available';
$lang['DT_PAGINATE_FIRST']  = 'First';
$lang['DT_PAGINATE_LAST']   = 'Last';
$lang['DT_PAGINATE_NEXT']   = 'Next';
$lang['DT_PAGINATE_PREV']   = 'Previous';

// ── HR module ─────────────────────────────────
$lang['PAGE_TITLE']         = 'DEQB STAFF REGISTER';
$lang['INFO']               = 'INFO';
$lang['HEADER_TITLE']       = 'Registration of DEQB researchers / collaborators';
$lang['INSTRUCTIONS']       = '<p>Please fill in the requested data to register (marked fields are mandatory).<br><br>In case of doubt please contact the secretariat.</p>';
$lang['SUCCESS']            = '<p>Your form has been successfully submitted. A confirmation will be sent to the provided email address.</p>';
$lang['ALT_EMAIL']          = 'Alternative email:';
$lang['PHONE']              = 'Phone contact (9 digits):';
$lang['EMERGENCY CONTACT']  = 'Emergency contact name:';
$lang['EMERGENCY CONTACT PHONE'] = 'Emergency contact phone (9 digits):';
$lang['PHONE_PRINT']        = 'Phone:';
$lang['WORK_RESP']          = 'Work supervisor:';
$lang['WORKSPACE_RESP']     = 'Workspace responsibles:';
$lang['WORK_RESP2']         = 'Work supervisor (not in previous list):';
$lang['UNIT']               = 'Research Unit:';
$lang['PROGROUP']           = 'Professional Group:';
$lang['BUILDING']           = 'Building';
$lang['DEQ_ACCESS']         = 'Request DEQB access (north door)?';
$lang['LAB_ACCESS']         = 'Choose the labs / offices you want to access:';
$lang['REQUESTED_ACCESS']   = 'Requested accesses:';
$lang['DOOR_ID']            = 'Door ID(s):';
$lang['SIGNATURE']          = 'Signature:';
$lang['DATA']               = 'Data:';
$lang['NEW_RECORD']         = 'New Record';
$lang['RECORD']             = 'REGISTRATION';
$lang['WORKPLACE']          = 'Workplace';
$lang['EXTENSION']          = 'Phone @FEUP';
$lang['OTHER']              = 'Other...';
$lang['OPTION']             = 'Choose an option';
$lang['OPT_YES']            = 'Yes';
$lang['OPT_NO']             = 'No';
$lang['ERR_CODE']           = 'The FEUP code entered is not valid';
$lang['ERR_DUPLICATE_CODE'] = 'FEUP code already exists. Contact the secretariat for changes.';
$lang['ERR_DATE']           = 'The end date cannot be earlier than the start date.';
$lang['ERR_NAME']           = 'Please enter the name';
$lang['ERR_EMAIL']          = 'Please enter a valid email address.';
$lang['ERR_EMAIL_MESSAGE']  = '<p><b>Error sending email. Your registration was not completed, please try again.</b></p>';
$lang['ERR_POSTAL_CODE']    = 'Postal code must be in format: XXXX-XXX';
$lang['ERR_PHONE']          = 'Phone number must have 9 digits';
$lang['ERR_WORK_RESP']      = 'Please enter the name of the work supervisor';
$lang['OTHER_ACCESS']       = 'Other accesses';
$lang['HEADER1']            = '<h3>1. Personal data</h3>';
$lang['HEADER2']            = '<h3>2. Registration</h3>';
$lang['HEADER3']            = '<h3>3. Accesses</h3>';
$lang['SUBJECT']            = 'Successful registration';
$lang['HR_TAB_NEW']         = 'New records';
$lang['HR_TAB_PENDING']     = 'Pending';
$lang['HR_TAB_EXPIRE']      = 'Expiring';
$lang['HR_TAB_ACTIVE']      = 'Active';
$lang['HR_TAB_INACTIVE']    = 'Inactive';
$lang['HR_TAB_REQUESTS']    = 'Requests';
$lang['HR_VALID_UNTIL']     = 'Valid until %s';
$lang['HR_CREATE_RECORD']   = 'Create staff registration';
$lang['HR_NO_RECORD']       = 'No record';
$lang['HR_REQUEST_EDIT']    = 'Request edit';
$lang['HR_REQUEST_CHANGE']  = 'Request change';
$lang['HR_EDIT_APPROVED']   = 'Edit approved — you can update your registration.';
$lang['HR_EDIT_PENDING']    = 'Edit request sent. Awaiting approval.';
$lang['HR_EDIT_CANCEL']     = 'Cancel request';
$lang['HR_PAGE_TITLE_ADMIN'] = 'New Record (Admin)';
$lang['HR_ADMIN_MODE_TITLE'] = 'Administrator Mode';
$lang['HR_ADMIN_MODE_MSG']   = 'This registration will be created on behalf of another collaborator. Fill in the UP Code, Name and Email of the person in question.';
$lang['HR_PAGE_TITLE_PROXY'] = 'Register on behalf of';
$lang['HR_PROXY_MODE_TITLE'] = 'Third-party registration';
$lang['HR_PROXY_MODE_MSG']   = 'You are filling in this registration on behalf of someone else. Notifications during the process will be sent to your email. Once the registration becomes active, the registered person will manage it directly.';
$lang['HR_PROXY_BTN']        = 'Register third party';
$lang['HR_ADMIN_CODE_HINT'] = 'Institutional code of the person (up…). On blur, name/email are auto-filled if the person already exists.';
$lang['HR_SECTION_PERSONAL']= 'Personal Details';
$lang['HR_SECTION_PERIOD']  = 'Period and Affiliation';
$lang['HR_SECTION_CLASS']   = 'Classification';
$lang['HR_SECTION_ACCESS']  = 'Access';
$lang['NONE_SELECTED']      = 'None selected';
$lang['OPTIONAL']           = 'optional';

// ── My record (HR self-service) ───────────────
$lang['HR_MY_RECORD']       = 'My Record';
$lang['HR_MY_RECORDS']      = 'My Records';
$lang['HR_NO_FEUP_CODE']    = 'Could not determine your FEUP code.';
$lang['HR_TIPO_NOVO']       = 'New record';
$lang['HR_TIPO_ALTERACAO']  = 'Data change';
$lang['HR_TIPO_NOVO_REG']   = 'Group change';
$lang['HR_TIPO_ALT_LABS']   = 'Lab access change';
$lang['HR_TIPO_ALT_DATAFIM']= 'End date change';
$lang['HR_TIPO_ALT_SIGARRA']= 'Change (to send to SIGARRA)';
$lang['HR_TIPO_MUDANCA_GRUPO'] = 'Professional group change';
$lang['HR_TIPO_RENOVACAO']  = 'Renewal / new period';
$lang['HR_PENDING_ANALYSIS']= 'Requests under review';
$lang['HR_SUBMITTED_AT']    = 'Submitted at';
$lang['HR_STATUS_SIGARRA']  = 'Awaiting SIGARRA';
$lang['HR_STATUS_ANALYSIS'] = 'Under review';
$lang['HR_REG_PROCESSING']  = 'Record being processed';
$lang['HR_REG_PROC_STATUS'] = 'A record with status <strong>%s</strong> is awaiting processing by the secretariat. No new changes can be submitted until it is activated.';
$lang['HR_RECORDS']         = 'Records';
$lang['HR_NO_NEW_REG']      = 'New record unavailable';
$lang['HR_COL_PROF_GROUP']  = 'Professional group';
$lang['HR_COL_BEGIN']       = 'Start';
$lang['HR_COL_END']         = 'End';
$lang['HR_COL_ACCESSES']    = 'Access';
$lang['HR_VIEW_DETAIL']     = 'View detail';
$lang['HR_IN_VALIDATION']   = 'In validation';
$lang['HR_PENDING_REQUEST'] = 'Pending request';
$lang['HR_WARN_GROUP']      = '<strong>Group change:</strong> a new record will be created and the current one will become Inactive once activated.';
$lang['HR_WARN_NEW_REG_MSG']= '<strong>New record:</strong> the current record will become Inactive once the new one is activated. Fill in the new dates.';
$lang['HR_WARN_LABS']       = 'Changes to lab access require secretariat approval. Other fields are applied immediately.';
$lang['HR_NAME_READONLY']   = 'The name cannot be changed here. Contact the secretariat.';
$lang['HR_NAME_HINT']       = 'To change name, email or UP code, contact the secretariat.';
$lang['HR_FORM_EMAIL_ALT']  = 'Alternative email';
$lang['HR_FORM_PHONE']      = 'Phone';
$lang['HR_FORM_UNIT']       = 'R&D Unit';
$lang['HR_SELECT']          = '— Select —';
$lang['HR_FORM_WORKPLACE']  = 'Workplace';
$lang['HR_FORM_EXT']        = 'FEUP Extension';
$lang['HR_OBS_PLACEHOLDER'] = 'Reason for request…';
$lang['HR_FORM_PROF_GROUP'] = 'Professional Group';
$lang['HR_FORM_RESP_OTHER'] = 'Responsible (other)';
$lang['HR_FORM_DEQ_ACCESS'] = 'DEQB access (north door)';
$lang['HR_FORM_LABS']       = 'Laboratories / offices';
$lang['HR_AUTO_ACCESS']     = 'Other accesses (automatically assigned):';
$lang['HR_PEDIR_NOVO']      = 'Request new record';
$lang['HR_RENOVAR']         = 'Renew access';
$lang['HR_SOLICITAR_RENOV'] = 'Request renewal';
$lang['HR_MSG_PESSOAL_OK']  = 'Personal data updated.';
$lang['HR_MSG_PESSOAL_ERR'] = 'Error updating personal data.';
$lang['HR_MSG_REG_OK']      = 'Registration data updated.';
$lang['HR_MSG_PED_UPDATED'] = 'Change request updated (date and/or accesses).';
$lang['HR_MSG_PED_SENT']    = 'Change request submitted for secretariat approval.';
$lang['HR_MSG_NO_CHANGE']   = 'No changes detected.';
$lang['HR_MSG_ERROR']       = 'Error processing the request. Please try again.';
$lang['HR_MSG_NOVO_GRUPO']  = 'New record request (professional group change) created and submitted for validation.';
$lang['HR_MSG_RENOVACAO']   = 'Renewal request created and submitted for validation.';
$lang['HR_MSG_NOVO_REG']    = 'New record request created and submitted for validation.';
$lang['HR_DATE_VALIDATION'] = 'End date cannot be earlier than start date.';

// ── Equipment module ──────────────────────────
$lang['EQUIP_TITLE']        = 'DEQB Equipment';
$lang['EQUIP_ADD']          = 'Add equipment';
$lang['EQUIP_EDIT']         = 'Edit equipment';
$lang['EQUIP_NEW']          = 'New equipment';
$lang['EQUIP_SEARCH']       = 'Search equipment or brand…';
$lang['EQUIP_ALL_LABS']     = 'All laboratories';
$lang['EQUIP_NAME']         = 'Equipment';
$lang['EQUIP_BRAND']        = 'Brand / Model';
$lang['EQUIP_YEAR']         = 'Year';
$lang['EQUIP_QTY']          = 'Qty.';
$lang['EQUIP_LAB']          = 'Laboratory';
$lang['EQUIP_MODEL']        = 'Model';
$lang['EQUIP_ACQ_YEAR']     = 'Acquisition year';
$lang['EQUIP_TECH']         = 'Technician';
$lang['EQUIP_CONDITIONS']   = 'Usage conditions';
$lang['EQUIP_SCHEDULE']     = 'Schedule';
$lang['EQUIP_SAMPLES']      = 'Sample types';
$lang['EQUIP_OPERATION']    = 'Operation';
$lang['EQUIP_COST']         = 'Cost';
$lang['EQUIP_PROCEDURE']    = 'Procedure';
$lang['EQUIP_IMAGE']        = 'Image';
$lang['EQUIP_REPLACE_IMG']  = 'Replace image';
$lang['EQUIP_REMOVE_IMG']   = 'Remove image';
$lang['EQUIP_IMG_HELP']     = 'JPEG, PNG, GIF or WebP — max. 2 MB';
$lang['EQUIP_IDENTIFICATION']= 'Identification';
$lang['EQUIP_ACCESS_TITLE'] = 'Specific access to this equipment';
$lang['EQUIP_ACCESS_GRANT'] = 'Grant access — numeric UP code';
$lang['EQUIP_GRANT']        = 'Grant';
$lang['EQUIP_NO_ACCESS']    = 'No specific access granted.';
$lang['EQUIP_GRANTED_BY']   = 'Granted by';
$lang['EQUIP_ADD_TO_LAB']   = 'Add equipment to this laboratory';

// ── Water module ──────────────────────────────
$lang['WATER_QC_TITLE']     = 'Distilled and purified water quality';
$lang['WATER_COND']         = 'Conductivity';
$lang['WATER_DEST']         = 'Distilled';
$lang['WATER_PUR']          = 'Purified';
$lang['WATER_LOG_TITLE']    = 'Log daily reading';
$lang['WATER_SAVE_READING'] = 'Save reading';
$lang['WATER_NO_DATA']      = 'No data for the selected period.';
$lang['WATER_CONSUMPTION']  = 'Ultrapure water consumption';
$lang['WATER_THIS_MONTH']   = 'Current month';
$lang['WATER_HISTORIC']     = 'Historical total';
$lang['WATER_NEW_RECORD']   = 'Insert new record';
$lang['infodeqb_water_respONSIBLE']  = 'Responsible';
$lang['WATER_USER']         = 'User';
$lang['WATER_QUANTITY']     = 'Quantity (L)';
$lang['WATER_MANAGE_USERS'] = 'Manage users';
$lang['WATER_NEW_RESP']     = 'Responsible';
$lang['WATER_NEW_USER']     = 'User';
$lang['WATER_EXPORT']       = 'Export';
$lang['WATER_BRAND']        = 'Brand';
$lang['WATER_MODEL']        = 'Model';
$lang['WATER_ACQ_DATE']     = 'Acquisition date';
$lang['WATER_ACQ']          = 'Acquisition';

// ── Period filter ─────────────────────────────
$lang['PERIOD_THIS_WEEK']   = 'This week';
$lang['PERIOD_THIS_MONTH']  = 'This month';
$lang['PERIOD_THIS_YEAR']   = 'This year';
$lang['PERIOD_LAST_7']      = 'Last 7 days';
$lang['PERIOD_LAST_15']     = 'Last 15 days';
$lang['PERIOD_LAST_30']     = 'Last 30 days';
$lang['PERIOD_CUSTOM']      = 'Custom';
$lang['PERIOD_FROM']        = 'From';
$lang['PERIOD_TO']          = 'To';
$lang['PERIOD_PREV']        = 'Previous period';
$lang['PERIOD_NEXT']        = 'Next period';

// ── Exams module ──────────────────────────────
$lang['EXAM_TITLE']         = 'Assessment Elements Archive';
$lang['EXAM_AUTO_TITLE']    = 'Archive Incorporation Record';
$lang['EXAM_TEACHER']       = 'Responsible teacher';
$lang['EXAM_COURSE_UNIT']   = 'Course units';
$lang['EXAM_TYPOLOGY']      = 'Typology';
$lang['EXAM_ACADEMIC_YEAR'] = 'Academic year';
$lang['EXAM_ADD_LINE']      = 'Add';
$lang['EXAM_SUBMIT_PDF']    = 'Submit and generate PDF';
$lang['EXAM_BOX']           = 'Box';
$lang['EXAM_CABINET']       = 'Cabinet';
$lang['EXAM_TICKET']        = 'Ticket / Date';
$lang['infodeqb_exam_archiveD']      = 'Archived';
$lang['EXAM_TO_ARCHIVE']    = 'To archive';
$lang['EXAM_NEW_AUTO']      = 'New incorporation record';
$lang['EXAM_INSTRUCTIONS']  = 'Instructions';
$lang['EXAM_LINE_ADDED']    = '%d line(s) added';
$lang['EXAM_MIN_LINE']      = 'Add at least one line before submitting.';
$lang['EXAM_ELIM_DOC']      = 'Elimination Delivery Record';
$lang['EXAM_TEACHER_LABEL'] = 'Teacher';
$lang['EXAM_BOX_N']         = 'Box #';
$lang['EXAM_CAB_N']         = 'Cabinet #';
$lang['EXAM_STEP_1']        = 'Organise the assessment items by academic year and course unit.';
$lang['EXAM_STEP_2']        = 'Fill in the form above. Each row may reference more than one course unit but <strong>only one academic year</strong>.';
$lang['EXAM_STEP_3']        = 'Click <strong>Submit</strong> — a PDF with the incorporation record will be generated and must be printed.';
$lang['EXAM_STEP_4']        = 'Hand in the duly labelled items at the secretariat together with the generated record.';
$lang['EXAM_STEP_5']        = 'Upon receipt, the record will be stamped, signed and returned.';
$lang['EXAM_STEP_6']        = 'At the end of year N, archives from academic year N&#8209;6/N&#8209;5 are sent to the FEUP Archive Service for disposal.';
$lang['EXAM_ARCHIVE_WARN']  = 'Only documents within the mandatory archiving period (5 years) should be delivered. If that period has elapsed, fill in the %s and request disposal from the Archive Service.';

// ── Teaching preference module ────────────────
$lang['SERVDOC_TITLE']      = 'Teaching Service Preference';
$lang['SERVDOC_SUBMIT']     = 'Submit preferences';
$lang['SERVDOC_UPDATE']     = 'Update preferences';
$lang['SERVDOC_MAX_UCS']    = 'Maximum of 10 course units reached.';
$lang['SERVDOC_SELECTED']   = '%d / 10 selected';
$lang['SERVDOC_SUBMITTED']  = 'Preferences submitted';
$lang['SERVDOC_EDIT_REQ']   = 'Request edit';
$lang['SERVDOC_EDIT_WAIT']  = 'Edit request sent. Awaiting approval.';
$lang['SERVDOC_EDIT_OK']    = 'Edit approved — you can update your preferences.';
$lang['SERVDOC_EDIT_NOW']   = 'Edit now';
$lang['SERVDOC_CANCEL_REQ'] = 'Cancel request';
$lang['SERVDOC_RESPONDED']  = 'Submitted';
$lang['SERVDOC_PENDING_U']  = 'Pending';
$lang['SERVDOC_APPROVE']    = 'Approve';
$lang['SERVDOC_REVOKE']     = 'Revoke';

// ── Scientific areas module ───────────────────
$lang['AREAS_TITLE']        = 'DEQB scientific areas and infodeqb_subareas';
$lang['AREAS_INSTRUCTIONS'] = 'Indicate your allocation to areas in multiples of 20% (total = 100%)';
$lang['AREAS_SUBMIT']       = 'Submit response';
$lang['AREAS_UPDATE']       = 'Update response';
$lang['AREAS_SUBMITTED']    = 'Response already submitted';
$lang['AREAS_NO_RESPONSE']  = 'No response';
$lang['AREAS_REPLACE']      = 'Replace response?';
$lang['AREAS_REPLACE_MSG']  = 'A response already exists. Current values will be replaced.';
$lang['AREAS_TOTAL_OK']     = 'Correct total: <strong>100%</strong>. You may submit.';
$lang['AREAS_TOTAL_WARN']   = 'Total must be <strong>100%</strong>. Current: <strong>%d</strong>%%';
$lang['AREAS_FILL']         = 'Fill in my response';
$lang['AREAS_FILL_EDIT']    = 'Edit my response';
$lang['AREAS_RESPONDED']    = 'Responded';
$lang['AREAS_MISSING']      = 'Pending';
$lang['AREAS_RATE']         = 'Response rate';
$lang['AREAS_DIST']         = 'FTE by area';
$lang['AREAS_HEATMAP']      = 'FTE by subarea × area';
$lang['AREAS_PARTICIPANTS'] = 'Participants';

// ── ADI module ────────────────────────────────
$lang['ADI_TITLE']          = 'Research Space Distribution';
$lang['ADI_SUBTITLE']       = 'Scientific Output and Management 2018–2024';
$lang['ADI_SCORE_CI']       = 'Scientific Output (P<sub>Ci</sub>)';
$lang['ADI_MANAGEMENT']     = 'Management';
$lang['ADI_PROJECTS']       = 'Projects';
$lang['ADI_AREA_CURRENT']   = 'Current area (m²)';
$lang['ADI_AREA_FORECAST']  = 'Forecast area (m²)';
$lang['ADI_PUBLICATIONS']   = 'Publications';
$lang['ADI_TRAINING']       = 'Training';
$lang['ADI_TRANSFER']       = 'Technology Transfer';
$lang['ADI_BACK_LIST']      = 'Back to list';
$lang['ADI_CRITERIA']       = 'Criteria';

// ── Mobility module ───────────────────────────
$lang['MOBILE_TITLE']       = 'Mobility & DIE';
$lang['MOBILE_IN_TITLE']    = 'Incoming Mobility — DEQB';
$lang['MOBILE_SUBTITLE']    = 'Mobility EQ · Company and institution contacts';
$lang['MOBILE_STUDENTS_IN'] = 'Incoming students';
$lang['MOBILE_COUNTRIES']   = 'Countries of origin';
$lang['MOBILE_DIE']         = 'DIE Contacts';
$lang['MOBILE_NEW_RECORD']  = 'New mobility record';
$lang['MOBILE_INCOMING']    = 'Incoming Mobility';
$lang['MOBILE_EDIT_RECORDS']= 'Edit records';
$lang['MOBILE_UNIVERSITY']  = 'University';
$lang['MOBILE_COUNTRY']     = 'Country';
$lang['MOBILE_PROGRAM']     = 'Programme';
$lang['MOBILE_CONTRACT']    = 'Type';
$lang['MOBILE_DURATION']    = 'Duration';
$lang['MOBILE_START']       = 'Start';
$lang['MOBILE_END']         = 'End';
$lang['MOBILE_UCS']         = 'Course units';
$lang['MOBILE_QUICK_ACCESS']= 'Quick access';
$lang['MOBILE_ADD_STUDENT'] = 'Add student';
$lang['MOBILE_DASHBOARD']   = 'Dashboard';

// ── Dashboard module subtitles ────────────────
$lang['MOD_HR_SUB']         = 'DEQB staff';
$lang['MOD_HR_ADMIN_SUB']   = 'Staff registration and management';
$lang['MOD_BOOKING_SUB']    = 'Shared rooms and equipment';
$lang['MOD_EQUIP_SUB']      = 'Equipment catalogue';
$lang['MOD_REAGENTS_SUB']   = 'Reagents inventory';
$lang['MOD_WATER_Q_SUB']    = 'Monitoring and records';
$lang['MOD_WATER_C_SUB']    = 'Ultrapure water — consumption';
$lang['MOD_EXAMS_SUB']      = 'Incorporation records';
$lang['MOD_MOBILE_SUB']     = 'Mobility EQ and DIE contacts';
$lang['MOD_AREAS_SUB']          = 'Department structure';
$lang['MOD_SERVDOC_SUB']        = 'Preference management';
$lang['MOD_SERVDOC_APPROVED']   = 'Approved — you may fill in now';
$lang['MOD_SERVDOC_PENDING']    = 'Request sent — awaiting approval';
$lang['MOD_AREAS_SUBMITTED']    = 'Response submitted';
$lang['MOD_TODO']               = 'Not filled in';
$lang['NAV_ADI']                = 'Research Spaces';
$lang['MOD_ADI_SUB']            = 'View my ADI profile';
// ── Dashboard section labels (admin stats) ───
$lang['DASH_LABEL_STAFF']       = 'Staff';
$lang['DASH_LABEL_EQUIPMENT']   = 'Equipment';
$lang['DASH_LABEL_EXAMS']       = 'Exam Archive';
$lang['DASH_LABEL_WATER']       = 'Water';
$lang['DASH_LABEL_MOBILITY']    = 'Mobility';
$lang['DASH_STAT_STAFF_ACTIVE'] = 'Active lecturers';
$lang['DASH_STAT_UCS']          = 'Course occurrences';
$lang['DASH_STAT_UCS_NO_SRV']   = 'Courses without service';
$lang['DASH_STAT_EQUIP_LABS']   = 'Equipment in my labs';
$lang['DASH_STAT_EXAMS_PEND']   = 'Records to archive';
$lang['DASH_STAT_WATER_LAST']   = 'Last reading';
$lang['DASH_STAT_WATER_MONTH']  = 'This month\'s consumption';
$lang['MOD_DSD_SUB']        = 'Distribution and reports';

// ── Section admin panel ───────────────────────
$lang['SEC_ADMIN_TITLE']    = 'Section admins';
$lang['SEC_ADMIN_INFO']     = 'Global admins are defined in <code>inc/admins.php</code> and are not managed here.';
$lang['SEC_ADMIN_CODE']     = 'UP Code';
$lang['SEC_ADMIN_ADDED_AT'] = 'Added on';
$lang['SEC_ADMIN_ADDED']    = 'Admin added.';
$lang['SEC_ADMIN_REMOVED']  = 'Admin removed.';
$lang['SEC_ADMIN_INVALID']  = 'Invalid module or code.';
$lang['SEC_ADMIN_CODE_PH']  = 'up356946 or up356946@up.pt';
$lang['SEC_ADMIN_NONE']     = 'No section admins defined.';
$lang['SEC_MODULE_HR']      = 'Staff (HR)';
$lang['SEC_MODULE_HR_LIST'] = 'Staff — list access';
$lang['MOD_HR_LIST_SUB']   = 'View active staff list';
$lang['SEC_MODULE_WATER']   = 'Ultrapure Water';
$lang['SEC_MODULE_EXAM']    = 'Exam Archive';
$lang['SEC_MODULE_MOBILE']  = 'Mobility EQ';

// ── Misc ──────────────────────────────────────
$lang['DENIED_TITLE']       = 'Access denied';
$lang['DENIED_MSG']         = 'You do not have permission to access this page.';
$lang['DENIED_REDIRECT']    = 'You will be redirected to the previous page in 5 seconds.';
$lang['DENIED_REDIRECT_HOME'] = 'You will be redirected to the home page in 20 seconds.';
$lang['DENIED_CONTACT']     = 'If in doubt, contact Luís Martins (<a href="mailto:fmartins@fe.up.pt">fmartins@fe.up.pt</a> | Ext.: 3613).';
$lang['LANG_SWITCH']        = 'Português';
$lang['LANG_SWITCH_URL']    = '?lang=pt';

// ── New section module keys ───────────────────
$lang['SEC_ADMIN_PAGE_TITLE'] = 'Section Admin Management';
$lang['SEC_MODULE_DSD']       = 'Teaching Service Distribution';
$lang['SEC_MODULE_GASES']     = 'Special Gases';

// ── ADI (individual records) ──────────────────
$lang['ADI_PAGE_TITLE']     = 'ADI Records';
$lang['ADI_SCORE_AREA']     = 'Score and Area';
$lang['ADI_PA_INDIVIDUAL']  = 'Pa individual';
$lang['ADI_PA_TOTAL']       = 'Pa total dept.';
$lang['ADI_PUB_HEADER']     = 'Publication';
$lang['ADI_DEQ_AUTHORS']    = 'DEQB Auth.';
$lang['ADI_STUDENT']        = 'Student';
$lang['ADI_ROLE']           = 'Role';
$lang['ADI_CONTRIB']        = 'Contrib.';
$lang['ADI_TITLE_HDR']      = 'Title';
$lang['ADI_FUNCTION']       = 'Function';
$lang['ADI_AREA_DEQ_DEF']   = 'DEQ+DEF Area';
$lang['ADI_AREA_DEF_ONLY']  = 'DEF Area Only';

// ── Scientific areas (extra) ──────────────────
$lang['AREAS_SUBTITLE']     = 'Association of faculty and career researchers';
$lang['AREAS_SUBAREA']      = 'Subarea';
$lang['AREAS_SUBTOTAL']     = 'Subtotal';
$lang['AREAS_MY_RESPONSE']  = 'My response';
$lang['AREAS_CHART_DESC']       = 'Bars: total ETI per area | Point ●: main area ETI';
$lang['AREAS_CHART_WITH_PCT']   = 'With % in this area';
$lang['AREAS_CHART_MAIN_AREA']  = 'Main area';
$lang['AREAS_RESPONSE']         = 'Response';
$lang['AREAS_FORM_STATUS']      = 'Submission status';
$lang['AREAS_FORM_STATUS_OPEN'] = 'Open';
$lang['AREAS_FORM_STATUS_CLOSED']= 'Closed';
$lang['AREAS_FORM_OPEN_BTN']    = 'Open submissions';
$lang['AREAS_FORM_CLOSE_BTN']   = 'Close submissions';
$lang['AREAS_FORM_CLOSED']      = 'Submissions closed';
$lang['AREAS_FORM_CLOSED_MSG']  = 'The form is temporarily closed. Please contact Luís Martins (<a href="mailto:fmartins@fe.up.pt">fmartins@fe.up.pt</a>) for more information.';
$lang['AREAS_FORM_BLOCKED']     = 'Submissions closed. It is not possible to save a response at this time.';
$lang['AREAS_FORM_ADMIN_NOTE']  = 'Note: submissions are closed for regular users.';

// ── Equipment (extra) ─────────────────────────
$lang['EQUIP_VIEW_DETAIL']       = 'View detail';
$lang['EQUIP_OP_DETAILS']        = 'Operational details';
$lang['EQUIP_NO_PERM']           = 'No permission to manage equipment in this lab.';
$lang['EQUIP_NAME_REQUIRED']     = 'Equipment name is required.';
$lang['EQUIP_CONFIRM_DELETE']    = 'Permanently delete this equipment?';
$lang['EQUIP_ACCESS_USER']       = 'User (UP)';
$lang['EQUIP_ADMIN_ONLY']        = 'only visible to global admin';
$lang['EQUIP_ACCESS_GRANTED_MSG']= 'Access granted.';
$lang['EQUIP_INVALID_CODE']      = 'Invalid UP code.';
$lang['EQUIP_ERROR_GRANT']       = 'Error granting access.';
$lang['EQUIP_ACCESS_REVOKED']    = 'Access revoked.';
$lang['EQUIP_LIST']              = 'List';

// ── Exam archive (extra) ──────────────────────
$lang['EXAM_SUBTITLE']       = 'Incorporation records and physical archive of assessment exams';
$lang['EXAM_ERROR_ADD']      = 'Error adding line.';
$lang['EXAM_TOTAL']          = 'Total records';
$lang['EXAM_ARCHIVED_LABEL'] = 'Archived';
$lang['EXAM_NONE']           = 'No records submitted.';
$lang['EXAM_EDIT_LINE']      = 'Edit line';
$lang['EXAM_DEL_LINE']       = 'Delete line';
$lang['EXAM_EXPAND_ALL']     = 'Expand all';
$lang['EXAM_COLLAPSE_ALL']   = 'Collapse all';
$lang['EXAM_ADD_LINES_HINT'] = 'Add lines above to create the incorporation record.';
$lang['EXAM_HOW_IT_WORKS']   = 'How it works';
$lang['EXAM_TEACHER_FIXED']  = 'Fixed for this record';

// ── Teaching service preference (extra) ───────
$lang['SERVDOC_PAGE_TITLE']    = 'Teaching Service Preference';
$lang['SERVDOC_PREFS_SAVED']   = 'Preferences saved successfully.';
$lang['SERVDOC_ERROR_SAVE']    = 'Error saving preferences.';
$lang['SERVDOC_NOT_AUTH']      = 'Edit not authorised.';
$lang['SERVDOC_MIN_UC']        = 'Select at least one course unit before submitting.';
$lang['SERVDOC_REQ_SENT']      = 'Request sent. You will be notified when editing is approved.';
$lang['SERVDOC_REQ_ERR']       = 'Error sending request.';
$lang['SERVDOC_REQ_CANCELLED'] = 'Edit request cancelled.';
$lang['SERVDOC_APPROVED']      = 'Edit approved.';
$lang['SERVDOC_REVOKED_MSG']   = 'Approval revoked.';

// ── Special Gases ─────────────────────────────
$lang['GASES_TITLE']         = 'Special Gases';
$lang['GASES_ADMIN_TITLE']   = 'Admin — Special Gases';
$lang['GASES_HISTORY_TITLE'] = 'History — Special Gases';
$lang['GASES_REPORT_TITLE']  = 'Reports — Special Gases';
$lang['GASES_RONDA_TITLE']   = 'New Round — Special Gases';

// ── Form misc ─────────────────────────────────
$lang['SELECT_OPTION']       = 'select';

// ── Dashboard and HR extra ────────────────────
$lang['DASH_TITLE']          = 'Home';
$lang['HR_LIST_TITLE']       = 'Personnel List';
$lang['HR_LIST_SUBTITLE']    = 'Active DEQB staff';
$lang['HR_MY_RECORD']        = 'My record';
$lang['HR_SUCCESS_TITLE']    = 'Registration Complete';
$lang['HR_RECORD_DETAIL']    = 'Record detail';

// ── Mobility ──────────────────────────────────
$lang['MOBILE_PAGE_TITLE']   = 'Mobility & DIE Contacts';
$lang['MOBILE_IN_TITLE']     = 'Mobility IN';
$lang['MOBILE_NEW_REQUEST']  = 'New Request — Mobility';
$lang['MOBILE_EDIT_REQUEST'] = 'Edit Request — Mobility';
$lang['MOBILE_DELETE_REQUEST']= 'Delete Record — Mobility';
$lang['MOBILE_DETAIL']       = 'Detail — Mobility';
$lang['MOBILE_DIE_TITLE']    = 'DIE Contacts';
$lang['MOBILE_EDIT_CONTACT'] = 'Edit Contact';

// ── Reagents and misc ─────────────────────────
$lang['REAGENTES_TITLE']       = 'Reagent Inventory — Teaching Laboratories and Warehouse';
$lang['REAGENTES_DESC']        = 'The list of reagents available in the DEQ teaching laboratories can be downloaded at the bottom of this page. Faculty and researchers who need them may request the loan of listed reagents from the laboratory technicians. Loans will be granted whenever possible.';
$lang['REAGENTES_FILE']        = 'File';
$lang['REAGENTES_DESCRIPTION'] = 'Description';
$lang['REAGENTES_FILE_DESC']   = 'List of reagents available in the DEQ teaching laboratories';
$lang['REAGENTES_FREQUENCY']   = 'Update Frequency';
$lang['REAGENTES_FREQ_VAL']    = 'At least annually';
$lang['REAGENTES_SIZE']        = 'Size';
$lang['REAGENTES_MODIFIED']    = 'Last modified';
$lang['REAGENTES_FILE_UNAVAIL']= 'File temporarily unavailable.';
$lang['REAGENTES_UPLOAD_TITLE']= 'Update inventory';
$lang['REAGENTES_UPLOAD_HINT'] = 'Upload an .xlsx file exported from Quartzy. Automatically replaces the previous one.';
$lang['REAGENTES_UPLOAD_OK']   = 'File uploaded successfully.';
$lang['REAGENTES_UPLOAD_ERR']  = 'Error saving the file. Check directory permissions.';
$lang['REAGENTES_UPLOAD_TYPE'] = 'Only .xlsx files are accepted.';
$lang['TIMEOUT_TITLE']       = 'Session warning';
$lang['EXAM_DELETE_TITLE']   = 'Delete Exam';
$lang['EXAM_ADMIN_DELETE_TITLE'] = 'Delete Exam — Administration';

// ── ADI extra ────────────────────────────────
$lang['ADI_UPDATING_TITLE']  = 'Under maintenance';

// ── HR Administration ─────────────────────────
$lang['HR_ADMIN_TITLE']      = 'Administration — Staff';
$lang['HR_ADMIN_DETAIL']     = 'Staff member detail';
$lang['HR_ADMIN_EDIT']       = 'Edit Staff member';
$lang['HR_ADMIN_EDIT_USER']  = 'Edit User';
$lang['HR_ADMIN_SPACES']     = 'Spaces and Supervisors';
$lang['HR_ADMIN_PRINT']      = 'Print Record';
