<!doctype html>
<html lang="en">
    <head>  
        <style>
            @page {
                size: A4 portrait;  /* Ensure portrait */
                margin: 15px;
            }
            body { margin: 15px; }
            table, td, th {
                border: 1.3px solid black;
                margin: 0px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }    
            th {
                padding: 8px;
            }
            td {
                padding: 3px 5px 2px;
                text-align: center;
            }
            .b {
                font-weight: bold;
            }
            td.calendar-noBorder {
                border: none;
                background-color: red;
            }
          
        </style>
    </head>
    <body>
        
        <?php 
            $lists = json_encode($lists); 
            $lists = json_decode($lists, true);  

            $employee = json_encode($user); 
            $employee = json_decode($user, true);  
        ?>
        
        <div style="float: left; width:46%; margin: 0;">
            <div style="font-family:Arial, Helvetica, sans-serif;">
                <p style="font-size: 9px; margin-left: 10px;"><i>CIVIL SERVICE FORM No.48</i></p>
                <h3 style="font-size: 12px; text-align: center;"><u>DAILY TIME RECORD</u></h3>
                <p style="font-size: 11px; text-align: center; margin-top: -7px;">{{ strtoupper($employee['firstname'].' '.$employee['middlename'].' '.$employee['lastname']) }}</p>
                <div style="border-bottom: 1px solid #333; width: 94%; margin-left: 10px; margin-top: -8px;"></div>
                <div style="font-size: 10px; width: 50%; float: left;">
                    <p style="margin-top: 2px; margin-left: 10px;">For the month of</p>
                    <p style="margin-left: 10px; margin-top: -5px; ">Official hours for arrival and departure</p>
                </div>
                <div style="font-size: 10px; width: 50%; float: right;">
                    <p style="margin-top: 2px; margin-left: 10px;">{{$month}}-{{$year}}</p>
                    <p style="margin-left: 10px; margin-top: -7px; ">Regular days: 8am-5pm</p>
                    <p style="margin-left: 10px; margin-top: -7px; ">Saturdays: not applicable</p>
                </div>
            </div>
            <table style="font-size: 9px; margin-top: 45px; padding: 10px; font-family:Arial, Helvetica, sans-serif">
                <thead>
                    <tr>
                        <td>Days</td>
                        <td colspan="2">A.M</td>
                        <td colspan="2">P.M</td>
                        <td colspan="2">UNDERTIME</td>
                    </tr>
                    <tr>
                        <td style="width: 8%;"></td>
                        <td style="width: 15%;">Arrival</td>
                        <td style="width: 15%;">Departure</td>
                        <td style="width: 15%;">Arrival</td>
                        <td style="width: 15%;">Departure</td>
                        <td style="width: 15%;">Hours</td>
                        <td style="width: 15%;">Minutes</td>
                    </tr>
                </thead>
                <tbody>
                @foreach($lists as $list)
                <tr class="text-center">
                    <td>{{ $loop->iteration }}</td>
                    @if($loop->iteration <= 15)
                        @if(!$list['is_with'])
                            <td width="80%" colspan="6" style="letter-spacing: 8px; font-size: 8px;">{{ $list['data'] }}</td>
                        @else
                            <td style="font-size: 8px;">{{ ($list['data']) ? $list['data']['am_in'] : '' }}</td>
                            <td style="font-size: 8px;">{{ ($list['data']) ? $list['data']['am_out'] : '' }}</td>
                            <td style="font-size: 8px;">{{ ($list['data']) ? $list['data']['pm_in'] : '' }}</td>
                            <td style="font-size: 8px;">{{ ($list['data']) ? $list['data']['pm_out'] : '' }}</td>
                            <td></td>
                            <td></td>
                        @endif
                    @else 
                        <td style="background: rgba(128,128,128, .5)"></td>
                        <td style="background: rgba(128,128,128, .5)"></td>
                        <td style="background: rgba(128,128,128, .5)"></td>
                        <td style="background: rgba(128,128,128, .5)"></td>
                        <td style="background: rgba(128,128,128, .5)"></td>
                        <td style="background: rgba(128,128,128, .5)"></td>
                    @endif
                </tr>
                @endforeach
                   
                </tbody>
            </table>
            <div style="width: 100%; font-family:Arial, Helvetica, sans-serif">
                <p style="font-size: 10px; margin-top: -2px; margin-left: 9px;">Total : <div style="border-bottom: 1px solid #333; width:86%; margin-top: -8px; margin-left: 40px;"></div></p>
                <p style="font-size: 10px; text-indent: 20px; width: 97%; margin-right: -10px; margin-left: 9px;">I CERTIFY on my honor that the above is a true and correct report of the hours of work peformed, record of which was made daily at the time of arrival at the departure from office.</p>
                <div style="border-bottom: 1px solid #333; width: 50%; float: right; margin-right: 10px; margin-top: 13px;"></div>
                <div style="border-bottom: 4px double #333; width: 94.5%; margin-left: 10px; margin-top: 30px;"></div>
                <center style="font-size: 10px; margin-top: 5px;">Verified as to the prescribed office hours</center>
                <div style="border-bottom: 1px solid #333; width: 50%; float: right; margin-right: 10px; margin-top: 20px;"></div>
                <p style="float: right; font-size: 11px; margin-right: 8px; margin-top: 22px">In Charge</p>
            </div>
        </div>
        <div style="float: right; width:46%; margin: 0;">
            <div style="font-family:Arial, Helvetica, sans-serif;">
                <p style="font-size: 9px; margin-left: 10px;"><i>CIVIL SERVICE FORM No.48</i></p>
                <h3 style="font-size: 12px; text-align: center;"><u>DAILY TIME RECORD</u></h3>
                <p style="font-size: 11px; text-align: center; margin-top: -7px;">{{ strtoupper($employee['firstname'].' '.$employee['middlename'].' '.$employee['lastname']) }}</p>
                <div style="border-bottom: 1px solid #333; width: 94%; margin-left: 10px; margin-top: -8px;"></div>
                <div style="font-size: 10px; width: 50%; float: left;">
                    <p style="margin-top: 2px; margin-left: 10px;">For the month of</p>
                    <p style="margin-left: 10px; margin-top: -5px; ">Official hours for arrival and departure</p>
                </div>
                <div style="font-size: 10px; width: 50%; float: right;">
                    <p style="margin-top: 2px; margin-left: 10px;">{{$month}}-{{$year}}</p>
                    <p style="margin-left: 10px; margin-top: -7px; ">Regular days: 8am-5pm</p>
                    <p style="margin-left: 10px; margin-top: -7px; ">Saturdays: not applicable</p>
                </div>
            </div>
            <table style="font-size: 9px; margin-top: 45px; padding: 10px; font-family:Arial, Helvetica, sans-serif">
                <thead>
                            <tr>
                        <td>Days</td>
                        <td colspan="2">A.M</td>
                        <td colspan="2">P.M</td>
                        <td colspan="2">UNDERTIME</td>
                    </tr>
                    <tr>
                        <td style="width: 8%;"></td>
                        <td style="width: 15%;">Arrival</td>
                        <td style="width: 15%;">Departure</td>
                        <td style="width: 15%;">Arrival</td>
                        <td style="width: 15%;">Departure</td>
                        <td style="width: 15%;">Hours</td>
                        <td style="width: 15%;">Minutes</td>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lists as $list)
                    <tr class="text-center">
                        <td>{{ $loop->iteration }}</td>
                        @if($loop->iteration > 15)
                            @if(!$list['is_with'])
                                <td width="80%" colspan="6" style="letter-spacing: 8px; font-size: 8px;">{{ $list['data'] }}</td>
                            @else
                                <td style="font-size: 8px;">{{ ($list['data']) ? $list['data']['am_in'] : '' }}</td>
                                <td style="font-size: 8px;">{{ ($list['data']) ? $list['data']['am_out'] : '' }}</td>
                                <td style="font-size: 8px;">{{ ($list['data']) ? $list['data']['pm_in'] : '' }}</td>
                                <td style="font-size: 8px;">{{ ($list['data']) ? $list['data']['pm_out'] : '' }}</td>
                                <td></td>
                                <td></td>
                            @endif
                        @else 
                            <td style="background: rgba(128,128,128, .5)"></td>
                            <td style="background: rgba(128,128,128, .5)"></td>
                            <td style="background: rgba(128,128,128, .5)"></td>
                            <td style="background: rgba(128,128,128, .5)"></td>
                            <td style="background: rgba(128,128,128, .5)"></td>
                            <td style="background: rgba(128,128,128, .5)"></td>
                        @endif
                    </tr>
                    @endforeach
                    
                </tbody>
            </table>
            <div style="width: 100%; font-family:Arial, Helvetica, sans-serif">
                <p style="font-size: 10px; margin-top: -2px; margin-left: 9px;">Total : <div style="border-bottom: 1px solid #333; width:86%; margin-top: -8px; margin-left: 40px;"></div></p>
                <p style="font-size: 10px; text-indent: 20px; width: 97%; margin-right: -10px; margin-left: 9px;">I CERTIFY on my honor that the above is a true and correct report of the hours of work peformed, record of which was made daily at the time of arrival at the departure from office.</p>
                <div style="border-bottom: 1px solid #333; width: 50%; float: right; margin-right: 10px; margin-top: 13px;"></div>
                <div style="border-bottom: 4px double #333; width: 94.5%; margin-left: 10px; margin-top: 30px;"></div>
                <center style="font-size: 10px; margin-top: 5px;">Verified as to the prescribed office hours</center>
                <div style="border-bottom: 1px solid #333; width: 50%; float: right; margin-right: 10px; margin-top: 20px;"></div>
                <p style="float: right; font-size: 11px; margin-right: 8px; margin-top: 22px">In Charge</p>
            </div>
        </div>
        
    </body>
</html>