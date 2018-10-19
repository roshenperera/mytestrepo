<?php

class JiraUtil
{
    private static $request = FALSE;
    const MAN_HOURS_PER_DAY = 8;

    public static function call_jira_api($url, $params = null)
    {
        if (!self::$request)
        {
            require_once('config.php');
            require_once('apprequest.php');
            self::$request = new BasicAuthAppRequest(JIRA_AUTH_USER_NAME, JIRA_AUTH_PASSWORD);
        }
        return json_decode(self::$request->exec_req($url, "GET", $params));
    }

    public static function calculate_job_score($est, $logged, $complexity, $quality)
    {
        if ($est == '-' || $quality == '-') {
            return 0;
        } else {
            return ($est / $logged) * $complexity * $quality;
        }
    }

    public static function calculate_time_factor($total_input, $available_days)
    {
        if ($available_days == 0) {
            return 0;
        }
        return $total_input / ($available_days * self::MAN_HOURS_PER_DAY * 3600);
    }

    public static function calculate_final_score($total_score, $s_value, $t_value)
    {
        $final_value = $total_score * $s_value * $t_value;

        return $final_value;
    }

    public static function get_time_components_for_days($time)
    {
        return self::get_time_components_for_seconds($time * self::MAN_HOURS_PER_DAY * 3600);
    }

    public static function get_time_components_for_seconds($time)
    {
        if (!is_numeric($time))
        {
            return '';
        }

        $timeinhours = (int)($time / 3600);
        $remm = (int)(($time % 3600) / 60);
        $remd = 0;
        $remw = 0;

        if ($timeinhours >= self::MAN_HOURS_PER_DAY) {
            $remh = $timeinhours % self::MAN_HOURS_PER_DAY;
            $timeindays = (int)($timeinhours / self::MAN_HOURS_PER_DAY);
            if ($timeindays >= 5) {
                $remd = $timeindays % 5;
                $remw = (int)($timeindays / 5);
            } else {
                $remd = $timeindays;
            }
        } else {
            $remh = $timeinhours;
        }
        $timecomp = array('w' => $remw, 'd' => $remd, 'h' => $remh, 'm' => $remm);
        return $timecomp;
    }

    public static function time_array_to_string($timecomparray)
    {
        if (!is_array($timecomparray)) {
            return '';
        }
        $ret = '';
        if ($timecomparray['w'] > 0) {
            $ret = $ret . $timecomparray['w'] . 'w ';
        }
        if ($timecomparray['d'] > 0) {
            $ret = $ret . $timecomparray['d'] . 'd ';
        }
        if ($timecomparray['h'] > 0) {
            $ret = $ret . $timecomparray['h'] . 'h ';
        }
        if ($timecomparray['m'] > 0) {
            $ret = $ret . $timecomparray['m'] . 'm';
        }

        return trim($ret);
    }

    public static function get_complexity_field($status)
    {
        switch ($status)
        {
            case '10468': //Designing InProgress
            case '10459': //Design Review
            case '10466': //Programming InProgress
            case '10451': //Code Review
                return 'customfield_11412'; //Prog/Design Complexity
            case '10454': //Testing InProgress
            case '10456': //Test Review
            case '10455': //Test Documentation
            case '10643': //Test Documentation Review
                return 'customfield_11414'; //Testing Complexity
            case '1': // Bug Report - this is a hardcoded value
                return 'customfield_11413';
            case '10544': //Report Review
                return 1; //Report Review Complexity
            default:
                return 0;
        }
    }

    public static function get_quality_field($status)
    {
        switch ($status)
        {
            case '10468': //Designing InProgress
            case '10459': //Design Review
            case '10466': //Programming InProgress
            case '10451': //Code Review
                return 'customfield_11407'; //Prog/Design Quality
            case '10454': //Testing InProgress
            case '10456': //Test Review
            case '10455': //Test Documentation
            case '10643': //Test Documentation Review
                return 'customfield_11408'; //Testing Quality
            case '1': // Bug Report - this is a hardcoded value
            case '10544': //Report Review
                return 'customfield_11701'; //QA Quality
            default:
                return 0;
        }
    }

    public static function get_time_estimation_field($status)
    {
        switch ($status)
        {
            case '10468': //Designing InProgress
            case '10466': //Programming InProgress
                return 'customfield_11409'; //Prog/Design Estimate
            case '10454': //Testing InProgress
            case '10455': //Test Documentation
                return 'customfield_11410'; //Testing Estimate
            case '10459': //Design Review
            case '10451': //Code Review
            case '10456': //Test Review
            case '10643': //Test Documentation Review
            case '10544': //Report Review
            case '1': //Open - new item
                return 1;
            default:
                return 0;
        }
    }

    public static function get_job_type_string($status)
    {
        switch ($status)
        {
            case '1': //Open - new item
                return 'Reporting';
            case '10468': //Designing InProgress
                return 'Designing';
            case '10459': //Design Review
                return 'Design Review';
            case '10466': //Programming InProgress
                return 'Programming';
            case '10451': //Code Review
                return 'Code Review'; //Prog/Design Quality
            case '10454': //Testing InProgress
                return 'Testing/Test Documentation';
            case '10456': //Test Review
                return 'Test Review/Test Documentation Review';
            case '10455': //Test Documentation
                return 'Testing/Test Documentation';
            case '10643': //Test Documentation Review
                return 'Test Review/Test Documentation Review';
            case '10544': //Report Review
                return 'Report Review';
            default:
                return 0;
        }
    }

    public static function get_work_log_time($status, $logged_times, $skip_first_index)
    {
        if (empty($logged_times))
        {
            return 0;
        }
        switch ($status)
        {
            case '1': //Open - new item
                return 0;
            case '10468': //Designing InProgress
            case '10459': //Design Review
            case '10466': //Programming InProgress
            case '10451': //Code Review
                return array_sum($logged_times);
            case '10454': //Testing InProgress
            case '10455': //Test Documentation
            case '10456': //Test Review
            case '10643': //Test Documentation Review
                if ($skip_first_index)
                {
                    return array_sum($logged_times) - $logged_times[0];
                }
                return array_sum($logged_times);
            case '10544': //Report Review
                return $logged_times[0];
            default:
                return 1;
        }
    }

    public static function get_complexity_mapping($complexity)
    {
        switch ($complexity) {
            case 1:
                return 1;
            case 2:
                return 1.25;
            case 3:
                return 1.5;
            case 4:
                return 1.75;
            case 5:
                return 2;
            default:
                return 0;
        }
    }

    public static function get_time_in_seconds($est)
    {
        $time_in_seconds = 0;

        $parts = preg_split('/\s+/', $est);

        $length = count($parts);
        for ($i = 0; $i < $length; $i++)
        {
            $rest = substr($parts[$i], -1);
            $seconds = 0;
            switch ($rest) {
                case "w":
                    $weeks = substr($parts[$i], 0, -1);
                    $seconds = $weeks * 3600 * 5 * self::MAN_HOURS_PER_DAY;
                    break;
                case "d":
                    $days = substr($parts[$i], 0, -1);
                    $seconds = $days * 3600 * self::MAN_HOURS_PER_DAY;
                    break;
                case "h":
                    $hours = substr($parts[$i], 0, -1);
                    $seconds = $hours * 3600;
                    break;
                case "m":
                    $minutes = substr($parts[$i], 0, -1);
                    $seconds = $minutes * 60;
                    break;
            }

            $time_in_seconds += $seconds;
        }

        return $time_in_seconds;
    }

    public static function is_task_completed($status)
    {
        if ($status == 'Development Completed' or $status == 'Verification' or $status == 'Ready for Documentation' or $status == 'Documentation Review' or $status == 'Documentation InProgress' or $status == 'Closed') {
            return true;
        } else {
            return false;
        }
    }

    public static function get_jql_params($jql)
    {
        return array("jql" => $jql, "fields" => "creator,status,changelog,fixVersions,versions,worklog,customfield_11407,customfield_11408,customfield_11409,customfield_11410,customfield_11412,customfield_11413,customfield_11414,customfield_11701", "expand" => "changelog", "maxResults" => 200);
    }
}