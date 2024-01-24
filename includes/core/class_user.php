<?php

class User {

    // GENERAL

    public static function user_info_by_id($id) {
        $q = DB::query("SELECT user_id, first_name, last_name, phone, email, plot_id
            FROM users WHERE user_id='".$id."' LIMIT 1;") or die (DB::error());

        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
            ];
        } else {
            return [
                'id' => 0,
                'plot_id' => 0,
                'first_name' => '',
                'last_name' => '',
                'phone' => 0,
                'email' => '',
            ];
        }
    }

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }


    public static function users_list($d = []) {
        $items = [];
        $where = [];
        $limit = 20;
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $sort = isset($d['selectUsers']) ? $d['selectUsers'] : '';

        if ($search) {
            $where[] = $sort . " LIKE '%".$search."%'";
        }
    
        $where = $where ? "WHERE " . implode(" AND ", $where) : "";
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login
            FROM users " . $where . "
            LIMIT " . $offset . ", " . $limit . ";") or die(DB::error());
    
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'last_login' => $row['last_login'],
            ];
        }
    
        // paginator
        $q = DB::query("SELECT count(*) FROM users " . $where . ";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search=' . $search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }
    

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_delete_window($d) {
        $user_id = isset($d['id']) ? (int) $d['id'] : 0;
        DB::query("DELETE FROM users WHERE user_id = " . $user_id) or die (DB::error());
        return User::users_fetch($d);
    }
    // ACTIONS

    public static function user_edit_window($d = []) {
        $id = isset($d['id']) ? (int)$d['id'] : 0;
        HTML::assign('user', User::user_info_by_id($id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        // vars
        $id = isset($d['id']) ? (float)$d['id'] : 0;
        $first_name = isset($d['first_name']) ? $d['first_name'] : '';
        $last_name = isset($d['last_name']) ? $d['last_name'] : '';
        $phone = isset($d['phone']) ? (int) preg_replace('/[^0-9]/', '', $d['phone']) : '';
        $email = isset($d['email']) ?  strtolower($d['email']) : '';
        $plot_id = isset($d['plot_id']) ? $d['plot_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // update
        if ($id) {
            $set = [];
            $set[] = "first_name='".$first_name."'";
            $set[] = "last_name='".$last_name."'";
            $set[] = "phone='".$phone."'";
            $set[] = "email='".$email."'";
            $set[] = "plot_id='".$plot_id."'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET ".$set." WHERE user_id='".$id."' LIMIT 1;") or die (DB::error());
        } else {
            $requiredFields = ['first_name', 'last_name', 'phone', 'email'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty($d[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                return [
                    'status_code' => 400, 
                    'msg' => 'The following fields must be filled in: ' . implode(', ', $missingFields)
                ];
            } else {
                DB::query("INSERT INTO users (
                    first_name,
                    last_name,
                    phone,
                    email,
                    plot_id
                ) VALUES (
                    '".$first_name."',
                    '".$last_name."',
                    '".$phone."',
                    '".$email."',
                    '".$plot_id."'
                );") or die (DB::error());
            }
        } 
        // output
        return User::users_fetch(['offset' => $offset]);
    }
}   