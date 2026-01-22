<?php
/**
* Template Name: My Sales Data
* Template Post Type: post, page
*/


global $wpdb;
$trip_data = $wpdb->get_results("
    SELECT sa.trip_code, sa.travel_date, wp.sale_price, (sa.stock - sa.pax) AS remaining, mid(sa.trip_code,9,2) as airline_code
    FROM {$wpdb->prefix}backend_manage_seat_availability sa
    LEFT JOIN {$wpdb->prefix}wt_price_category_relation wp 
      ON sa.pricing_id = wp.pricing_id AND wp.pricing_category_id = '953'
    WHERE sa.travel_date > CURRENT_DATE AND (sa.stock - sa.pax) > 0 and wp.sale_price is not null
    ORDER BY cast(wp.sale_price as float) ASC
");


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sales Agent Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- orrect Bootstrap 4.5.2 version -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- jQuery & Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


   <style type="text/css">
        .combine-deal-custom.wptravel-layout-v2 .title-section h2 {
    text-align: center;
    margin-bottom: 50px;
}

.combine-deal-custom.wptravel-layout-v2 {
    margin-top: 100px;
    margin-bottom: 100px;
}

.flightsForm {
    position: relative;
    width: 100%;
    background-color: #ffc107;
    padding: 25px 15px 30px;
    color: #1c2b39;
    border-radius: 5px;
    display: inline-block;
}

.custom-select, .custom-select-options .scroll-holder {
    background: 0 0;
}

.main_search_flight_div {
    width: 100%;
    padding: 40px 6px 15px;
}

.flight_section_top_left label {
    display: inline-block;
    cursor: pointer;
    font-weight: 500;
    position: relative;
    overflow: hidden;
    margin-bottom: .375em;
    font-size: 14px;
}

.flight_section_top_left label input {
    position: absolute;
    left: -9999px;
}

.flight_section_top_left label input:checked + span {
    background-color: #d6d6e5;
}

.flight_section_top_left label input:checked + span:before,
.inner_booking_from .main_search_flight_div .flight_section_top_left label input:checked + span:before {
    box-shadow: inset 0 0 0 .4375em #00005c;
}

.flight_section_top_left label span {
    display: flex;
    align-items: center;
    padding: .375em .75em .375em .375em;
    border-radius: 99em;
    transition: .25s;
}

.flight_section_top_left label span:before {
    display: flex;
    flex-shrink: 0;
    content: "";
    background-color: #fff;
    width: 1.5em;
    height: 1.5em;
    border-radius: 50%;
    margin-right: .375em;
    transition: .25s;
    box-shadow: inset 0 0 0 .125em #00005c;
}

#flg_wg_month, .filed_input input {
    border: 1px solid #bcd5f5;
}

.flight_section_top {
    max-width: 990px;
    margin: 0 auto;
    display: block;
}

.flight_section_top_left {
    margin-bottom: 20px;
    float: left;
    margin-right: 10px;
}

.flight_section_top_right {
    float: right;
    margin-right: 0;
}

#serviceClass, .arrow_single, .dropdown.wrapper, .field-text_hiiden, .leave_on {
    display: none;
}

.service-class {
    margin-left: 20px;
    float: left;
    position: relative;
}

.custom-select {
    width: auto;
    float: right;
    padding: 0;
    border: none;
    color: #1c2b39;
    font-size: 14px;
    line-height: 24px;
    margin-left: 10px;
}

.filed_input input, .new_form_bottom {
    width: 100%;
    display: inline-block;
}

.filed_input input, .required {
    font-size: 12px;
    line-height: 24px;
}

.required {
    float: left;
    padding-bottom: 0;
}

.left_data {
    float: left;
    display: inline-block;
    width: 84%;
}

.filed_input {
    float: left;
    display: inline-block;
    width: 29%;
    margin-right: 6px;
    position: relative;
}

.spacer_icon {
    width: 20px;
    float: left;
    display: inline-block;
    position: relative;
    font-size: 14px;
}

.spacer_icon i {
    position: absolute;
    left: -21px;
    top: 34px;
    color: #15223f;
}

.filed_input input {
    height: 40px;
    padding: 7px 9px;
    margin: 0;
    background-color: #fff;
    color: #1c2b39;
    border-radius: 3px;
    outline: 0;
    cursor: pointer;
}

.stepper a, .stepper span {
    float: left;
    height: 20px;
    text-align: center;
}

.filed_input label {
    font-size: 14px;
}

.filed_input.trip_date {
    width: 18%;
    position: relative;
    cursor: pointer;
}

.filed_input.trip_date input {
    padding-left: 30px;
}

.trip_date i {
    position: absolute;
    left: 10px;
    top: 34px;
    color: #15223f;
}

.right_data {
    width: 16%;
    display: flex;
    float: left;
}

.filed_input.trip_passanger {
    width: 81px;
    position: relative;
    cursor: pointer;
}

.filed_input.trip_passanger .fa-angle-down {
    position: absolute;
    right: 10px;
    top: 37px;
    display: block;
    font-size: 18px;
    color: #1c2b39;
}

.filed_input.submitbutton {
    margin-right: 0;
    margin-top: 34px;
}

.filed_input.submitbutton button {
    height: 40px;
    line-height: 40px;
    padding: 0;
    width: 50px;
    margin: 0;
    background: #212529;
    border-radius: 3px;
}

.fl_from_date {
    margin-right: 30px !important;
}

ul.autocomplete li {
    padding: 0;
    margin: 0;
}

ul.autocomplete {
    padding: 0;
    width: 100% !important;
    z-index: 11;
}

label.error {
    position: absolute;
    margin: 5px 0 10px;
    background: #da4c4b;
    padding: 8px 15px;
    display: block;
    color: #fff;
    font-size: 12px;
    border-radius: 4px;
    line-height: 13px;
    clear: both;
    width: 100%;
}

label.error:before {
    content: "";
    display: block;
    position: absolute;
    height: 0;
    width: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-bottom: 8px solid #da4c4b;
    border-top: 0 solid transparent;
    left: 10px;
    top: -6px;
}

input.error {
    color: #da4c4b;
}

.fa_close_date {
    right: 6px !important;
    left: inherit !important;
    padding-left: 10px;
    display: block;
}

.fa_close_date:after {
    content: "";
    position: absolute;
    border-left: 1px solid #bcd5f5;
    width: 1px;
    height: 38px;
    top: -7px;
    left: 2px;
}

.custom-select .field-holder {
    padding-right: 20px !important;
    width: 100%;
    position: relative;
    cursor: pointer;
    z-index: 1;
    height: auto;
    padding: 0 7px;
    font-size: 12px;
    font-weight: 400;
}

.custom-select .field-holder:before {
    content: "";
    display: block;
    z-index: 900;
    height: 0;
    width: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 4px solid #000;
    position: absolute;
    top: 50%;
    margin-top: -2px;
    right: 6px;
}

.custom-select select.behind {
    display: inline;
    position: absolute;
    top: 5px;
    left: 5px;
    height: 1px;
    z-index: 0;
    opacity: 0;
    filter: Alpha(Opacity=0);
    font-size: 12px;
    width: 100%;
    border: 0;
}

.custom-select .field-holder .field-text:hover {
    text-decoration: underline;
}

.custom-select .field-holder .field-text, .custom-select-options li.selected {
    font-weight: 700;
}

.custom-select-options {
    display: none;
    background: #fff;
    border-radius: 3px;
    z-index: 3;
    position: absolute;
    margin: 5px 0 0;
    padding: 0;
    right: 30px;
    top: 55px;
}

.custom-select-options .triangle {
    content: "";
    position: absolute;
    display: block;
    height: 0;
    width: 0;
    border-left: 9px solid transparent;
    border-right: 9px solid transparent;
    border-bottom: 10px solid #bcd5f5;
    top: -9px;
    z-index: 1;
    right: 93px;
}

.custom-select-options .triangle:before, .dropdown > .content > .arrow:before {
    content: " ";
    z-index: 1999;
    display: block;
    font-size: 0;
    height: 0;
    width: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 9px solid #fff;
    border-top: 0 solid transparent;
    margin-left: -8px;
    margin-top: 1px;
}

.custom-select-options ul {
    border-radius: 3px;
    border: 1px solid #bcd5f5;
    padding: 10px 0;
    margin: 0;
}

.custom-select-options li {
    display: block;
    clear: both;
    padding: 8px 15px;
    color: #1c2b39;
    cursor: pointer;
    white-space: nowrap;
    font-size: 12px;
}

.dropdown.wrapper {
    position: absolute;
    border: 1px solid #bcd5f5;
    padding: 0;
    margin: 0;
    z-index: 100;
    border-radius: 3px;
    background: #fff;
    right: -95px;
}

.dropdown > .content {
    position: relative;
    display: table;
}

.dropdown > .content > .arrow {
    position: absolute;
    display: block;
    height: 0;
    width: 0;
    border-left: 9px solid transparent;
    border-right: 9px solid transparent;
    border-bottom: 10px solid #bcd5f5;
    top: -10px;
    left: 132px;
}

.pax-counter {
    width: 280px;
    background: #fff;
    padding: 20px;
    position: static;
    opacity: 1;
    display: block;
}

.pax-counter .pax:first-child {
    border-top: 0;
    padding-top: 0;
    margin-top: 0;
}

.pax-counter .pax {
    padding-top: 9px;
    margin-top: 9px;
    border-top: 1px solid #dcdee3;
    overflow: hidden;
}

.pax-counter .pax > p {
    color: #1c2b39;
    font-size: 16px;
    display: block;
    font-weight: 700;
    float: left;
    width: 60%;
    text-align: left;
    line-height: 1;
}

.stepper, .stepper a {
    display: inline-block;
}

.pax-counter .pax > p span {
    display: block;
    padding-top: 5px;
    font-size: 12px;
    color: #969dac;
    font-weight: 400;
}

.pax-counter .pax .stepper {
    margin-top: 5px;
}

.stepper {
    border-radius: 4px;
    overflow: hidden;
}

.btn.function.ghost.small {
    line-height: 28px;
    padding: 0 19px;
}

.btn.function.ghost {
    background-color: transparent;
    border: 2px solid #0775e2;
    color: #0775e2;
}

.pax-counter .close-pax-counter {
    width: 100%;
    margin-top: 20px;
    text-align: center;
    text-decoration: none;
    display: block;
}

.stepper a {
    width: 20px;
    line-height: 22px;
    color: #fff;
    background: #0775e2;
    font-size: 7px;
}

.stepper span {
    position: relative;
    top: -1px;
    width: 50px;
    padding: 0;
    border: 0;
    color: #002172;
    font-size: 18px;
}

.multi_stpes a, .multi_stpes input[type=radio] {
    display: inline-block;
    cursor: pointer;
    font-weight: 500;
    position: relative;
    overflow: hidden;
    margin-bottom: 6px !important;
    font-size: 14px;
    text-decoration: none;
    color: #1c2b39;
    text-align: right;
    float: right;
}

.daterangepicker td.off,
.daterangepicker td.off.end-date,
.daterangepicker td.off.in-range,
.daterangepicker td.off.start-date {
    background-color: #fff;
    border-color: transparent;
    color: #000 !important;
}

.inner_booking_from,
.search_form_new_design form.custom-bg-header-in-form {
    background-color: rgb(0 0 0) !important;
}

.inner_booking_from {
    padding: 15px 5px 5px;
    border-radius: 5px;
}

.inner_booking_from .main_search_flight_div {
    padding: 20px 0 0;
}

.inner_booking_from .main_search_flight_div .flightsForm {
    padding: 0;
}

.inner_booking_from .main_search_flight_div .flightsForm .flight_section_top {
    max-width: 100%;
    padding: 20px;
}

.inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .flight_section_top_left {
    padding-left: 3px;
    margin-bottom: 0;
}

.inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .flight_section_top_right {
    padding-right: 2px;
    display: flex;
    flex-direction: row;
}

.inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom {
    padding: 0 8px;
}

.inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom .filed_input.submitbutton {
    width: 3.88em;
}

.inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom .filed_input.submitbutton button.btn {
    width: 100%;
}

.inner_booking_from .main_search_flight_div .flightsForm .flight_section_top_left label span:before {
    width: 1em;
    height: 1em;
}

.filed_input.trip_passanger.show_options .dropdown.wrapper,
.flight_section_top_right.show_menu .custom-select-options.service-class-holder {
    display: block;
}

.new_custom_travel_section .wpb_column.vc_column_container.vc_col-sm-4 {
    position: relative;
    z-index: 0;
}

@media only screen and (max-width: 1040px) {
    .flight_section_top .left_data .filed_input {
        width: 23%;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom .filed_input.submitbutton {
        width: 3.4em;
    }
}

@media only screen and (max-width: 991px) {
    .left_data, .right_data {
        width: 100%;
        float: none;
        display: inline-flex;
    }

    .flight_section_top .left_data .filed_input {
        width: 30%;
        float: none;
    }

    .flight_section_top .left_data .filed_input.trip_date:last-child {
        margin-right: 0 !important;
    }

    .flight_section_top .spacer_icon {
        display: none;
    }

    .fl_from_date {
        margin-right: 6px !important;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom label.error {
        position: unset;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom label.error:before {
        top: auto;
        bottom: 30px;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom .dropdown.wrapper {
        right: -200px;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom .dropdown.wrapper .content > .arrow {
        left: 35px;
    }
}

@media only screen and (max-width: 640px) {
    .flight_section_top_left label {
        font-size: 12px;
    }
}

@media only screen and (max-width: 575px) {
    .filed_input.trip_passanger .fa-angle-down {
        top: 28px !important;
    }

    .flight_section_top_left label {
        font-size: 12px;
        margin: 0;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .flight_section_top_left {
        margin: 0;
        float: none;
        padding: 0;
        display: inline-flex;
        flex-flow: row wrap;
        width: 100%;
        align-items: center;
    }

    label.multi_stpes a {
        margin: 0 !important;
        font-size: 12px;
    }

    .flight_section_top_right {
        float: none;
        margin-right: 0;
        width: 100%;
        display: inline-block;
        padding: 0;
    }

    .left_data, .right_data {
        flex-flow: row wrap;
    }

    .fl_from_date {
        margin-right: 0 !important;
    }

    .flight_section_top .left_data .filed_input {
        width: 100%;
        margin-bottom: 10px;
        margin-right: 0;
    }

    .flight_section_top .filed_input label {
        font-size: 12px;
        margin-bottom: 2px;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom {
        padding: 0;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom .left_data .filed_input.trip_date,
    .inner_booking_from .right_data .filed_input {
        width: 49%;
    }

    .inner_booking_from .trip_date i {
        top: 32px;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom .filed_input.submitbutton {
        width: 49%;
        margin: 0;
    }

    .inner_booking_from .right_data {
        align-items: flex-end;
    }

    .flight_section_top .left_data .filed_input.trip_date:last-child {
        margin-left: 6px;
    }

    .inner_booking_from .main_search_flight_div .flightsForm .flight_section_top .new_form_bottom .dropdown.wrapper {
        right: -132px;
    }
}

.book_your_tour:after,
.booking_title span,
.custom-bg-header-inn .booking_title.book_your_pack:after,
.main_booking_from .booking_title.book_your_trip:after,
.show_booking_form {
    display: none;
}

.full_s {
    border: none;
    flex-flow: row wrap;
    justify-content: center;
}

form.custom-bg-header-in-form .book_trip_pack_section {
    margin-top: 4px;
}

.booking_title {
    border: 1px solid #fb0;
    margin: 0 10px 10px !important;
    padding: 4px 9px;
    font-size: 11px !important;
    text-align: center;
}

.book_your_trip {
    line-height: 32px !important;
}

.wpb_text_column.wpb_content_element.hide_booking_form form.custom-bg-header-in-form .book_trip_pack_section .full_s .booking_title.book_your_pack,
.wpb_text_column.wpb_content_element.show_booking_form.show_fm_mobile .full_s .booking_title.book_your_trip,
div#inner_booking_froms .full_s .booking_title.book_your_tour {
    background: #fb0;
    color: #fff !important;
}

.wpb_text_column.wpb_content_element.show_booking_form.show_fm_mobile .full_s .booking_title.book_your_tour {
    background: 0 0 !important;
}

.hide_booking_form {
    display: block;
}

.footer_grey_section_in .widget {
    width: 100%;
}

    </style>
   
</head>
<body>

<div class="container mt-4">

  <!-- Dashboard Tabs -->
  <ul class="nav nav-tabs mb-3" id="dashboardTabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link" id="caller-tab" data-toggle="tab" href="#caller" role="tab">Caller Overview</a>
    </li>      
    <li class="nav-item">
      <a class="nav-link active" id="performance-tab" data-toggle="tab" href="#performance" role="tab">Agent Performance</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="leads-tab" data-toggle="tab" href="#leads" role="tab">Leads to Follow Up</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="pax-tab" data-toggle="tab" href="#pax" role="tab">Purchased Pax</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="aftercall-tab" data-toggle="tab" href="#aftercall" role="tab">After Call Notes</a>
    </li>
  </ul>

  <!-- Tab Content -->
  <div class="tab-content" id="dashboardTabsContent">

    <!-- Performance -->
    <div class="tab-pane fade show active" id="performance" role="tabpanel">
      <div class="row">
        <div class="col-md-4">
          <div class="card text-white bg-info mb-3">
            <div class="card-body">
              <h5 class="card-title">Total Leads</h5>
              <p class="card-text display-4">32</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-white bg-success mb-3">
            <div class="card-body">
              <h5 class="card-title">Converted</h5>
              <p class="card-text display-4">14</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-white bg-warning mb-3">
            <div class="card-body">
              <h5 class="card-title">Pending Callbacks</h5>
              <p class="card-text display-4">5</p>
            </div>
          </div>
        </div>
      </div>
      <div class="card p-3">
        <h5>Monthly Conversions</h5>
        <!--<img src="https://via.placeholder.com/600x200?text=Conversion+Chart" class="img-fluid">-->
      </div>
    </div>

    <!-- Leads -->
    <div class="tab-pane fade" id="leads" role="tabpanel">
      <div class="alert alert-warning">🔔 <strong>Reminder:</strong> You have 3 callbacks today and 1 overdue follow-up!</div>

      <div class="row mb-3">
        <div class="col-md-4">
          <input type="text" class="form-control" placeholder="Search by name or contact">
        </div>
        <div class="col-md-3">
          <select class="form-control">
            <option value="">All Statuses</option>
            <option>Quote Sent</option>
            <option>Callback Requested</option>
            <option>Converted</option>
          </select>
        </div>
        <div class="col-md-3">
          <input type="month" class="form-control">
        </div>
        <div class="col-md-2">
          <button class="btn btn-secondary btn-block">Filter</button>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><strong>Leads to Follow Up</strong></div>
        <div class="card-body table-responsive p-0">
          <table class="table table-striped mb-0">
            <thead class="thead-dark">
              <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Follow-up</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Rajiv Singh</td>
                <td>+61 410 123 456</td>
                <td><span class="badge badge-warning status-pill">Quote Sent</span></td>
                <td>13 June 2025</td>
                <td>
                  <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewModalRajiv">View</button>
                  <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#convertModalRajiv">Convert</button>
                  <button class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#logCallModalRajiv">Log Call</button>
                  <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#scheduleCallbackModalRajiv">Schedule Callback</button>
                </td>
              </tr>
              <!-- Add more demo rows here if needed -->
            </tbody>
          </table>
        </div>
      </div>
       <!-- View Modal -->
    <div class="modal fade" id="viewModalRajiv" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Passenger Info: Rajiv Singh</h5>
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <table class="table table-bordered table-sm">
              <tr><th>Contact</th><td>+61 410 123 456</td></tr>
              <tr><th>Preferred Route</th><td>Melbourne → Delhi</td></tr>
              <tr><th>Travel Month</th><td>July 2025</td></tr>
              <tr><th>Passengers</th><td>2 Adults, 1 Child</td></tr>
              <tr><th>Quoted Price</th><td>$2,400 AUD</td></tr>
              <tr><th>Remarks</th><td>Asked to confirm by Friday. Wants refund policy.</td></tr>
              <tr><th>Callback</th><td>14 June 2025, 11:00 AM</td></tr>
              <tr><th>Assigned Agent</th><td>Tanvi</td></tr>
            </table>
            <h6 class="mt-3">Activity Timeline</h6>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">12 June: Lead added to system</li>
              <li class="list-group-item">13 June: Call made, quote sent</li>
              <li class="list-group-item">14 June: Callback scheduled</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Convert Lead Modal -->
<div class="modal fade" id="convertModalRajiv" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h7 class="modal-title">Convert Lead: Rajiv Singh</h7>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form>
        <div class="modal-body">
          <div class="form-group">
            <label>Booking Amount (AUD)</label>
            <input type="number" class="form-control" placeholder="Enter amount">
          </div>
          <div class="form-group">
            <label>Payment Status</label>
            <select class="form-control">
              <option>Paid</option>
              <option>Partially Paid</option>
              <option>Unpaid</option>
            </select>
          </div>
          <div class="form-group">
            <label>Booking Reference</label>
            <input type="text" class="form-control" placeholder="e.g. GTX20250613-21">
          </div>
          <div class="form-group">
            <label>Remarks (Optional)</label>
            <textarea class="form-control" rows="3" placeholder="e.g. Paid via Stripe, PDF ticket sent"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Confirm Conversion</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Log Call Modal -->
<div class="modal fade" id="logCallModalRajiv" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Log Call: Rajiv Singh</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form>
        <div class="modal-body">
          <div class="form-group">
            <label>Call Outcome</label>
            <select class="form-control">
              <option>Spoke - Sent Quote</option>
              <option>No Answer - Left Voicemail</option>
              <option>Requested Callback</option>
              <option>Interested - Need Follow-Up</option>
            </select>
          </div>
          <div class="form-group">
            <label>Call Notes</label>
            <textarea class="form-control" rows="3" placeholder="Summary of what was discussed..."></textarea>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="quoteSent">
            <label class="form-check-label" for="quoteSent">Quote Sent</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Log</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Schedule Callback Modal -->
<div class="modal fade" id="scheduleCallbackModalRajiv" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Schedule Callback: Rajiv Singh</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form>
        <div class="modal-body">
          <div class="form-group">
            <label>Callback Date & Time</label>
            <input type="datetime-local" class="form-control">
          </div>
          <div class="form-group">
            <label>Reason</label>
            <textarea class="form-control" rows="3" placeholder="e.g. Waiting for family confirmation"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">Schedule</button>
        </div>
      </form>
    </div>
  </div>
</div>
    </div>

    <!-- Purchased Pax -->
    <div class="tab-pane fade" id="pax" role="tabpanel">
      <div class="card">
        <div class="card-header bg-success text-white"><strong>Pax Who Have Purchased</strong></div>
        <div class="card-body table-responsive p-0">
          <table class="table table-striped mb-0">
            <thead class="thead-light">
              <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Route</th>
                <th>Travel Date</th>
                <th>Payment</th>
                <th>Booking Ref</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Rajiv Singh</td>
                <td>+61 410 123 456</td>
                <td>Melbourne → Delhi</td>
                <td>22 July 2025</td>
                <td><span class="badge badge-success">Paid</span></td>
                <td>GTX20250722-RJ</td>
                <td><button class="btn btn-sm btn-info" data-toggle="modal" data-target="#paxModalRajiv">View</button></td>
              </tr>
              <tr>
                <td>Anjali Mehra</td>
                <td>+61 433 456 789</td>
                <td>Sydney → Ahmedabad</td>
                <td>30 August 2025</td>
                <td><span class="badge badge-success">Paid</span></td>
                <td>GTX20250830-AM</td>
                <td><button class="btn btn-sm btn-info" data-toggle="modal" data-target="#paxModalAnjali">View</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Pax Modal: Rajiv -->
<div class="modal fade" id="paxModalRajiv" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h8 class="modal-title">Pax Info: Rajiv Singh</h8>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm">
          <tr><th>Route</th><td>Melbourne → Delhi</td></tr>
          <tr><th>Travel Date</th><td>22 July 2025</td></tr>
          <tr><th>Passengers</th><td>2 Adults, 1 Child</td></tr>
          <tr><th>Booking Reference</th><td>GTX20250722-RJ</td></tr>
          <tr><th>Ticket Status</th><td>Issued</td></tr>
          <tr><th>Payment</th><td>Paid via Stripe ($2,400 AUD)</td></tr>
          <tr><th>Special Requests</th><td>Vegetarian Meal, Window Seat</td></tr>
        </table>
        <h6 class="mt-3">After-Sales Notes</h6>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">Ticket sent on 15 July</li>
          <li class="list-group-item">Called to confirm baggage policy</li>
        </ul>
        <!--Previous booking-->

<h7 class="mt-4">Previous Bookings</h6>
<table class="table table-sm table-bordered">
  <thead class="thead-light">
    <tr>
      <th>Booking Ref</th>
      <th>Route</th>
      <th>Travel Date</th>
      <th>Amount</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>
        <button class="btn btn-link p-0" onclick="openNestedModal('paxModalRajiv', 'bookingModal1')">
          GTX20231212-RJ
        </button>
      </td>
      <td>Melbourne → Mumbai</td>
      <td>12 Dec 2023</td>
      <td>$1,850</td>
      <td><span class="badge badge-success">Completed</span></td>
    </tr>
    <tr>
      <td>
        <button class="btn btn-link p-0" onclick="openNestedModal('paxModalRajiv', 'bookingModal1')">
           GTX20240718-RJ
        </button>
      </td>
      <td>Melbourne → Delhi</td>
      <td>18 July 2024</td>
      <td>$2,120</td>
      <td><span class="badge badge-success">Completed</span></td>
    </tr>
  </tbody>
</table>
<!-- Nested Booking Modal -->
<div class="modal fade" id="bookingModal1" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Booking Details: GTX20231212-RJ</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm">
          <tr><th>Route</th><td>Melbourne → Mumbai</td></tr>
          <tr><th>Travel Date</th><td>12 Dec 2023</td></tr>
          <tr><th>Passengers</th><td>2 Adults</td></tr>
          <tr><th>Payment</th><td>Paid via Stripe ($1,850 AUD)</td></tr>
          <tr><th>Ticket Status</th><td>Issued</td></tr>
        </table>

        <h6>After-Sales Notes</h6>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">Confirmed by call on 5 Dec</li>
          <li class="list-group-item">PDF ticket sent</li>
        </ul>
      </div>
    </div>
  </div>
</div>


      </div>
    </div>
  </div>
</div>
    </div>
    
    
    <!-- Caller Overview Tab -->
      
<?php
// Step 1: Load booking data BEFORE checking it
require_once get_template_directory() . '/templates/G360_Dashboard/Agent_Performance_View/my-data/get-my-sales-data-information.php';

$caller_phone = '21659041'; // or dynamically detect from call log or GET/POST 34533500 55365626
$booking = get_caller_latest_booking($caller_phone); 
$orders = [$booking];
?>

<?php if (!empty($booking)) : 
    $full_name = trim("{$booking->salutation} {$booking->fname} {$booking->lname}");
    $formatted_date = date('d F Y', strtotime($booking->last_booking));
    $travel_date_raw = $booking->travel_date ?? ''; // Ensure this is available from DB
    $travel_date_timestamp = strtotime($travel_date_raw);
    $travel_date = date('d F Y', $travel_date_timestamp);
    $today = strtotime(date('Y-m-d'));
    $total_pax = $booking->total_pax ?? '';
    $order_id = $booking->order_id ?? '';

    $trip_code = $booking->trip_code ?? '';
    $from_code = strtoupper(substr($trip_code, 0, 3));
    $to_code   = strtoupper(substr($trip_code, 4, 3));
    $airlines   = strtoupper(substr($trip_code, 8, 2));
    $route = "{$from_code} → {$to_code} → {$airlines} ";
?>

<div class="tab-pane fade show active" id="caller" role="tabpanel">
  <div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-1"><?= esc_html($full_name) ?></h5>
        <p class="mb-0 text-muted">
          📞 <a href="#" data-toggle="modal" data-target="#passengerModal"><?= esc_html($booking->phone_pax_cropped) ?></a>
        </p>
        <br>
        <button class="btn btn-primary">SL</button>
        <button class="btn btn-outline-secondary">Non-SL</button>
      </div>
      <div>
        <?php
        $is_upcoming = $travel_date_timestamp > $today;
        ?>
    
        <strong><?= $is_upcoming ? 'Upcoming trip:' : 'Last booking:' ?></strong>
        <?= esc_html($formatted_date) ?><br>
    
        <strong>Order ID:</strong><a href="#" data-toggle="modal" data-target="#modalOrder<?= esc_html($order_id) ?>">
    <?= esc_html($order_id) ?>
</a>
<br>
        <strong>Total Pax:</strong> <?= esc_html($total_pax) ?><br>
        <strong><?= $is_upcoming ? 'Upcoming Trip' : 'Route' ?>:</strong>
        <a href="#" data-toggle="modal" data-target="#routeModal">
          <?= esc_html($route) ?>
        </a><br>
        <strong>Travel Date:</strong> <?= esc_html($travel_date) ?>
    </div>
    </div>
  </div>

<?php else: ?>
<div class="tab-pane fade show active" id="caller" role="tabpanel">
  <div class="alert alert-warning">No recent bookings found for this caller.</div>
</div>
<?php endif; ?>

<!--Other passenger modal-->

<?php
$other_passengers = get_other_passengers_by_phone($booking->phone_pax_cropped);
?>

<div class="modal fade" id="passengerModal" tabindex="-1" role="dialog" aria-labelledby="passengerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Other Travellers Linked to Phone no <?= esc_html($booking->phone_pax_cropped) ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <?php if (!empty($other_passengers)): ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Name</th>
              <th>DOB</th>
              <th>Relationship</th>
              <th>Primary?</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($other_passengers as $p): ?>
              <tr>
                <td><?= esc_html("{$p->salutation} {$p->fname} {$p->lname}") ?></td>
                <td><?= esc_html(date('d M Y', strtotime($p->dob))) ?></td>
                <td><?= esc_html($p->relationship ?: '-') ?></td>
                <td><?= $p->is_primary ? '<span class="badge badge-success">Yes</span>' : '-' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p>No additional passengers found.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!--order information modal-->  
<?php foreach ($orders as $order): ?>
  <?php
    $paxList = get_all_pax_by_order_id($order->order_id); // Call your actual function
    $modal_id = 'modalOrder' . esc_attr($order->order_id);
  ?>
  <div class="modal fade" id="<?= $modal_id ?>" tabindex="-1" role="dialog" aria-labelledby="<?= $modal_id ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Passenger Info – Order ID: <?=esc_html($order_id) ?></h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <?php if (!empty($paxList)): ?>
            <table class="table table-bordered table-sm">
              <thead class="thead-light">
                <tr>
                  <th>Name</th>
                  <th>DOB</th>
                  <th>Phone</th>
                  <th>Wheelchair</th>
                  <th>Meal</th>
                  <th>Order Type</th>
                  <th>Order Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($paxList as $pax): ?>
                  <tr>
                    <td><?= esc_html("{$pax->salutation} {$pax->fname} {$pax->lname}") ?></td>
                    <td><?= esc_html($pax->dob) ?></td>
                    <td><?= esc_html($pax->phone_pax_cropped) ?></td>
                    <td><?= esc_html($pax->wheelchair ?: 'No') ?></td>
                    <td><?= esc_html($pax->meal ?: 'No Preference') ?></td>
                    <td><?= esc_html($pax->order_type) ?></td>
                    <td><?= esc_html(date('d M Y', strtotime($pax->order_date))) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="alert alert-warning mb-0">No passenger data available for this order.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<style>
.modal-content {
    font-size: 16px;
}

.modal-body table td,
.modal-body table th {
    padding: 12px 16px;
    font-size: 15px;
}

.modal-title {
    font-size: 30px;
    font-weight: 600;
}
#returnDateRow {
  flex-wrap: wrap;
}
</style>

  
  
<!-- Modal: Round-Trip Seat Search -->
<div class="modal fade" id="routeModal" tabindex="-1" role="dialog" aria-labelledby="routeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Search Round Trip Seat Availability</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
      </div>
      <div class="modal-body">
        <form id="routeSearchForm">
          <div class="form-group">
            <label for="searchRoute">Route (e.g., DEL-MEL):</label>
            <input type="text" name="search_route" id="searchRoute" class="form-control" required>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4">
              <label for="travelStart">Start Date:</label>
              <input type="date" name="travel_start" id="travelStart" class="form-control" required>
            </div></div>
            <div class="form-row" id="returnDateRow">
  <div class="form-group col-md-4">
    <label for="travelEnd">End Date:</label>
    <input type="date" name="travel_end" id="travelEnd" class="form-control">
  </div>
</div>
<div class="form-group col-md-4">
    <label for="flexReturn">Flexible Return Days:</label>
    <select id="flexReturn" name="flex_return" class="form-control">
      <option value="0">Exact</option>
      <option value="3">±3 Days</option>
      <option value="7" selected>±7 Days</option>
      <option value="15">±15 Days</option>
    </select>
  </div>
          <div class="form-group">
            <label for="minPax">Minimum Seats Required:</label>
            <input type="number" name="min_pax" id="minPax" class="form-control" min="1" value="1" required>
          </div>
          <div class="form-group">
          <label for="airlineFilter">Preferred Airline (optional):</label>
          <input type="text" name="airline" id="airlineFilter" class="form-control" placeholder="e.g. QF, AI, SQ">
        </div>
          <div class="form-check">
              <input type="checkbox" id="oneWayCheckbox" class="form-check-input">
              <label class="form-check-label" for="oneWayCheckbox">One-way only</label>
            </div>
          <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <div id="routeResults" class="mt-4"></div>
      </div>
    </div>
  </div>
</div>

<script>
const tripData = <?= json_encode($trip_data) ?>;

document.getElementById("oneWayCheckbox").addEventListener("change", function () {
  const isChecked = this.checked;
  document.getElementById("returnDateRow").style.display = isChecked ? "none" : "flex";
});

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("routeSearchForm");
  const resultsContainer = document.getElementById("routeResults");

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    searchTrips();
  });

  function searchTrips() {
      const isOneWay = document.getElementById("oneWayCheckbox").checked;
      const travelStart = new Date(document.getElementById("travelStart").value);
      const minPax = parseInt(document.getElementById("minPax").value);
      const route = document.getElementById("searchRoute").value.trim().toUpperCase();
      const [fromCode, toCode] = route.split("-").map(x => x.trim());
      const reversedRoute = `${toCode}-${fromCode}`;
      const airlinePref = document.getElementById("airlineFilter").value.trim().toUpperCase();
    
      let travelEnd = isOneWay ? null : new Date(document.getElementById("travelEnd").value);
      const flexDays = parseInt(document.getElementById("flexReturn").value);
    
      const minDepartDate = new Date(travelStart);
      minDepartDate.setDate(minDepartDate.getDate() - flexDays);
      const maxDepartDate = new Date(travelStart);
      maxDepartDate.setDate(maxDepartDate.getDate() + flexDays);
    
      const minReturnDate = isOneWay ? null : new Date(travelEnd);
      if (minReturnDate) minReturnDate.setDate(minReturnDate.getDate() - flexDays);
      const maxReturnDate = isOneWay ? null : new Date(travelEnd);
      if (maxReturnDate) maxReturnDate.setDate(maxReturnDate.getDate() + flexDays);
    
      const departures = tripData.filter(row => {
        const date = new Date(row.travel_date);
        const airlineMatches = !airlinePref || (row.airline_code && row.airline_code.toUpperCase().includes(airlinePref));
        return row.trip_code.includes(`${fromCode}-${toCode}`) &&
          date >= minDepartDate &&
          date <= maxDepartDate &&
          parseInt(row.remaining) >= minPax &&
          airlineMatches;
      });
    
      let allRows = [];
    
      departures.forEach(depart => {
        if (isOneWay) {
          allRows.push({
            depart,
            return: null,
            isCheapest: false
          });
        } else {
          const returns = tripData.filter(ret => {
            const retDate = new Date(ret.travel_date);
            const airlineMatches = !airlinePref || (ret.airline_code && ret.airline_code.toUpperCase().includes(airlinePref));
            return ret.trip_code.includes(reversedRoute) &&
              retDate >= minReturnDate &&
              retDate <= maxReturnDate &&
              parseInt(ret.remaining) >= minPax &&
              airlineMatches;
          });
    
          const cheapestPrice = returns.length > 0 ? Math.min(...returns.map(r => parseFloat(r.sale_price))) : null;
    
          if (returns.length === 0) {
            allRows.push({ depart, return: null, isCheapest: false });
          } else {
            returns.forEach(ret => {
              allRows.push({
                depart,
                return: ret,
                isCheapest: parseFloat(ret.sale_price) === cheapestPrice
              });
            });
          }
        }
      });
    
      if (allRows.length === 0) {
        resultsContainer.innerHTML = `<div class="alert alert-warning">No matching trips found.</div>`;
        return;
      }
    
      renderPaginatedRows(allRows);
    }


  function renderPaginatedRows(allRows) {
    const perPage = 5;
    let currentPage = 1;
    const totalPages = Math.ceil(allRows.length / perPage);

    function renderPage(page) {
      let html = `<table class="table table-bordered table-sm">
        <thead><tr><th>Depart</th><th>Return</th><th>Total Price</th></tr></thead><tbody>`;

      const start = (page - 1) * perPage;
      const pageRows = allRows.slice(start, start + perPage);

      pageRows.forEach(item => {
        if (!item.return) {
          html += `<tr>
            <td><strong>${item.depart.trip_code}</strong><br>${item.depart.travel_date}<br>
              <span>Price: ${item.depart.sale_price}</span><br>
              <span>Seats: ${item.depart.remaining}</span>
            </td>
            <td colspan="3" class="text-muted">No return options</td>
          </tr>`;
        } else {
          html += `<tr>
            <td><strong>${item.depart.trip_code}</strong><br>${item.depart.travel_date}<br>
              <span>Price: ${item.depart.sale_price}</span><br>
              <span>Seats: ${item.depart.remaining}</span>
            </td>
            <td><strong${item.isCheapest ? ' class="text-success"' : ''}>${item.return.trip_code}</strong><br>
              ${item.return.travel_date}<br>
              <span>Price: ${item.return.sale_price}</span><br>
              <span>Seats: ${item.return.remaining}</span>
            </td>  
            <td>
            <strong>Total Price: ${(parseFloat(item.depart.sale_price) + parseFloat(item.return.sale_price)).toFixed(2)}</strong><td>
          </tr>`;
        }
      });

      html += "</tbody></table>";

      // Pagination controls
      html += `<nav><ul class="pagination justify-content-center">`;
      for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}">
                  <a class="page-link" href="#" data-page="${i}">${i}</a>
                 </li>`;
      }
      html += `</ul></nav>`;

      resultsContainer.innerHTML = html;

      resultsContainer.querySelectorAll(".page-link").forEach(link => {
        link.addEventListener("click", function (e) {
          e.preventDefault();
          currentPage = parseInt(this.getAttribute("data-page"));
          renderPage(currentPage);
        });
      });
    }

    renderPage(currentPage);
  }
});
</script>





  
<!-- Auto-pop Reminder and AI Tip Box -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const phoneNumber = '+61 410 123 456';
    const loggedCaller = '+61 410 123 456';

    if (phoneNumber === loggedCaller) {
      let reminders = [
        'Ask about August fares — Rajiv usually travels then.',
        'This is the time Rajiv usually requests a callback — check schedule.',
        'Last year Rajiv enquired about family discounts in June.'
      ];
      let tips = [
        'Mention Qantas baggage policy — was important last time.',
        'Offer Emirates as an option — Rajiv chose them for flexibility before.',
        'Highlight refund options — noted concern on last call.'
      ];

      let reminderBox = document.getElementById('dynamicReminder');
      let tipBox = document.getElementById('dynamicTip');
      let i = 0;

      function updateAlerts() {
        reminderBox.innerHTML = '<strong>Reminder:</strong> ' + reminders[i % reminders.length];
        tipBox.innerHTML = '<strong>AI Tip:</strong> ' + tips[i % tips.length];
        i++;
      }

      updateAlerts();
      reminderBox.parentElement.classList.add('slide-in');
      tipBox.parentElement.classList.add('slide-in');
      setInterval(updateAlerts, 6000);
    }
  });
</script>


<!-- Auto-pop Reminder and AI Tip Box -->
<div class="row">
  <div class="col-md-6">
    <div class="alert alert-info d-flex align-items-center caller-reminder-box" role="alert">
      <i class="mr-2 fas fa-bell"></i>
      <div id="dynamicReminder"></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="alert alert-secondary d-flex align-items-center caller-reminder-box" role="alert">
      <i class="mr-2 fas fa-lightbulb"></i>
      <div id="dynamicTip"></div>
    </div>
  </div>
</div>

  
  <!-- 2. Booking & Travel History -->
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">📈 Quick Insights</div>
        <div class="card-body">
          <p><strong>Total Bookings:</strong> 12</p>
          <p><strong>Total Revenue:</strong> $21,000</p>
          <h6 class="mt-3">📄 Recent Provided Quotes</h6>
        <table class="table table-sm table-bordered mb-3">
          <thead class="thead-light">
            <tr>
              <th>ID</th>  
              <th>Date</th>
              <th>Route</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
              <button class="btn btn-link p-0" data-toggle="modal" data-target="#quoteModal1">
                QTE20240613-01
              </button>
            </td>  
              <td>13 June</td>
              <td>MEL → DEL</td>
              <td>$2,400</td>
              <td><span class="badge badge-warning">Pending</span></td>
            </tr>
            <tr>
              <td>2</td>    
              <td>01 May</td>
              <td>MEL → BOM</td>
              <td>$1,280</td>
              <td><span class="badge badge-success">Accepted</span></td>
            </tr>
            <tr>
              <td>3</td>    
              <td>15 Mar</td>
              <td>SYD → AMD</td>
              <td>$1,990</td>
              <td><span class="badge badge-danger">Declined</span></td>
            </tr>
          </tbody>
        </table>
          <h6 class="mt-4">📞 Last 3 Calls</h6>
            <table class="table table-sm table-bordered mb-0">
              <thead class="thead-light">
                <tr>
                  <th>ID</th>
                  <th>Date</th>
                  <th>Campaign</th>
                  <th>Duration</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                  <button class="btn btn-link p-0" data-toggle="modal" data-target="#callModal1">
                    1
                  </button>
                </td>
                  <td>14 June</td>
                  <td>GTIB</td>
                  <td>6m 12s</td>
                  <td><span class="badge badge-success">Completed</span></td>
                </tr>
                <tr>
                  <td>2</td>
                  <td>13 June</td>
                  <td>GTCS</td>
                  <td>2m 45s</td>
                  <td><span class="badge badge-warning">Follow-up</span></td>
                </tr>
                <tr>
                  <td>3</td>
                  <td>12 June</td>
                  <td>GTPY</td>
                  <td>0m 00s</td>
                  <td><span class="badge badge-danger">Missed</span></td>
                </tr>
              </tbody>
            </table>
        </div>
      </div>
    </div>

<!-- Quote Details Modal -->
<div class="modal fade" id="quoteModal1" tabindex="-1" role="dialog" aria-labelledby="quoteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Quote Details: QTE20240613-01</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm">
          <tr><th>Date Provided</th><td>13 June 2025</td></tr>
          <tr><th>Route</th><td>Melbourne → Delhi</td></tr>
          <tr><th>Passengers</th><td>2 Adults, 1 Child</td></tr>
          <tr><th>Quote Amount</th><td>$2,400 AUD</td></tr>
          <tr><th>Status</th><td><span class="badge badge-warning">Pending</span></td></tr>
          <tr><th>Remarks</th><td>Waiting for family confirmation</td></tr>
          <tr><th>Created By</th><td>Tanvi</td></tr>
          <tr><th>Sent Via</th><td>WhatsApp</td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Call Details Modal -->
<div class="modal fade" id="callModal1" tabindex="-1" role="dialog" aria-labelledby="callModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Call Details: 14 June (GTIB)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm">
          <tr><th>Campaign</th><td>GTIB</td></tr>
          <tr><th>Date</th><td>14 June 2025</td></tr>
          <tr><th>Start Time</th><td>11:04 AM</td></tr>
          <tr><th>Duration</th><td>6 minutes 12 seconds</td></tr>
          <tr><th>Agent</th><td>Tanvi</td></tr>
          <tr><th>Status</th><td><span class="badge badge-success">Completed</span></td></tr>
          <tr><th>Summary</th><td>Discussed fare options and scheduled a callback for follow-up.</td></tr>
        </table>
      </div>
    </div>
  </div>
</div>
    <div class="col-md-6">
      <div class="card mb-3">
      <div class="card-header">📋 Preferences & Notes</div>
      <div class="card-body">
        <p><strong>Meal:</strong> Vegetarian (prefers Indian option)</p>
        <p><strong>Seat:</strong> Aisle seat for long flights</p>
        <p><strong>Special Assistance:</strong> Needs wheelchair for mother if traveling</p>
        <p><strong>Preferred Airlines:</strong> Emirates (extra baggage), Qantas (frequent flyer)</p>
        <p><strong>Booking Style:</strong> Usually books 2–3 weeks before travel, flexible by ±3 days</p>
        <p><strong>Behavior:</strong> Price sensitive, asks about refund policies</p>
        <p><strong>Communication:</strong> Prefers WhatsApp for quotes and PDF invoices</p>
        <p><strong>Tags:</strong> 🧳 Baggage-Conscious, 📅 Date Flexibility, 👪 Group Travel Potential</p>
      </div>
    </div>

    </div>
  </div>

  <!-- 3. New Quote Form -->
  
  
  <div class="main_booking_from">
            <div class="inner_booking_from" id="inner_booking_froms">
            <div class="full_s">
                <div class="booking_title book_your_trip">
                    <i class="fa fa-plane" aria-hidden="true"></i> Flights
                </div>
            </div>
            <div class="main_search_flight_div defaultform">
                <div class="flightsForm">
                    <div class="container">
                        <form name="flight_from_new" class="flight_from_new">
                            <div class="flight_section_top">
                                <div class="flight_section_top_left">
                                    <label>
                                        <input type="radio" name="radio" checked value="One-way trip">
                                        <span>One Way</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="radio" value="Return trip">
                                        <span>Round Trip</span>
                                    </label>
                                    <label>
                                        <input type="checkbox" name="studentfare" id="studentfare" value="studentfare">
                                        <span>Student fares</span>
                                    </label>
                                    <label class="multi_stpes">
                                        <input type="radio" name="radiomulticity" value="Multi city/Stopovers">
                                        <span><a href="/stopovers">Multi city/Stopovers</a></span>
                                    </label>
                                </div>
                                <div class="flight_section_top_right">
                                    <div class="wrap airline-select">
                                        <label for="airlineInput" class="required">Airline:</label>
                                        <div class="custom-airline-dropdown">
                                            <div id="airlineDisplay">
                                                <span id="airlineSelected">Select an Airline</span>
                                                <span style="margin-left: 5px;">▼</span>
                                            </div>
                                            <ul id="airlineOptions">
                                                <li class="dropdown-option" data-value="AI" data-label="Air India">Air India</li>
                                                <li class="dropdown-option" data-value="UL" data-label="SriLankan Airlines">SriLankan Airlines</li>
                                                <li class="dropdown-option" data-value="QF" data-label="Qantas">Qantas</li>
                                                <li class="dropdown-option" data-value="MH" data-label="Malaysia Airlines">Malaysia Airlines</li>
                                                <li class="dropdown-option" data-value="SQ" data-label="Singapore Airlines">Singapore Airlines</li>
                                                <li class="dropdown-option" data-value="CY" data-label="Cyprus Airways">Cyprus Airways</li>
                                                <li class="dropdown-option" data-value="VJ" data-label="VietJet Air">VietJet Air</li>
                                                <li class="dropdown-option" data-value="TR" data-label="Scoot">Scoot</li>
                                                <li class="dropdown-option" data-value="JQ" data-label="Jetstar">Jetstar</li>
                                            </ul>
                                        </div>
                                        <input type="hidden" name="airline" id="airlineInput" value="">
                                    </div>
                                    <div class="wrap service-class">
                                        <label for="serviceClass" class="required">class:</label>
                                        <div class="custom-select" data-sn="1">
                                            <select id="serviceClass" name="sc" class="behind">
                                                <option value="E">Economy</option>
                                                <option value="P" selected>Premium economy</option>
                                                <option value="B">Business class</option>
                                                <option value="F">First class</option>
                                            </select>
                                            <div class="field-holder">
                                                <div class="field-text">Premium economy</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="new_form_bottom">
                                <div class="left_data">
                                    <div class="filed_input fl_from_date">
                                        <label>Depart from</label>
                                        <input type="text" name="from_contry" class="flg_from_country" id="from_name_new" autocomplete="off" value="">
                                    </div>
                                    <div class="spacer_icon">
                                        <i class="fa fa-long-arrow-right arrow_single" aria-hidden="true"></i>
                                    </div>
                                    <div class="filed_input">
                                        <label>Flying to</label>
                                        <input type="text" name="flg_to_country" class="flg_to_country" id="to_name_new" autocomplete="off" value="">
                                    </div>
                                    <div class="filed_input trip_date">
                                        <label>Departure date</label>
                                        <input id="depart-date" type="text" name="travel_date" class="flg_startdate" autocomplete="off" value="17-06-25">
                                        <i class="fa fa-calendar" aria-hidden="true"></i>
                                    </div>
                                    <div class="filed_input trip_date">
                                        <label>Return date</label>
                                        <input id="return-date" type="text" name="flg_todate" class="flg_todate" autocomplete="off" value="" disabled>
                                        <i class="fa fa-calendar" aria-hidden="true"></i>
                                    </div>
                                </div>
                                <div class="right_data">
                                    <div class="filed_input trip_passanger">
                                        <label>Passengers</label>
                                        <input type="text" name="flg_ptype" class="flg_ptype" placeholder="1" value="1">
                                        <i class="fa fa-angle-down" aria-hidden="true"></i>
                                    </div>
                                    <div class="filed_input submitbutton">
                                        <button type="button" class="btn transaction qsf-search">
                                            <i class="fa fa-search" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
</div>
    <!-- 4. Tools Panel -->
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">📊 Gdeals Fare Lookup Tool</div>
        <div class="card-body">
          <form class="row">
        <div class="col-md-3">
          <label>From</label>
          <input type="text" class="form-control" value="Melbourne">
        </div>
        <div class="col-md-3">
          <label>To</label>
          <input type="text" class="form-control" value="Delhi">
        </div>
        <div class="col-md-3">
          <label>Dep Date</label>
          <input type="date" class="form-control">
        </div>
          <button type="button" class="btn btn-secondary">Search Fare</button>
        </div>
      </form>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">📦 Booking History</div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">GTX20250722-RJ – MEL → DEL – Paid – $2,400</li>
          <li class="list-group-item">GTX20240718-RJ – MEL → DEL – Completed</li>
          <li class="list-group-item">GTX20231212-RJ – MEL → BOM – Completed</li>
        </ul>
      </div>
    </div>


<!--After Call Notes-->
<div class="tab-pane fade" id="aftercall" role="tabpanel">
  <div class="card">
    <div class="card-header bg-light">
      <strong>📝 After Call Notes</strong>
    </div>
    <div class="card-body">
      <form>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Meal Preference</label>
            <input type="text" class="form-control" placeholder="e.g. Vegetarian, Indian">
          </div>
          <div class="form-group col-md-4">
            <label>Seat Preference</label>
            <select class="form-control">
              <option>Aisle</option>
              <option>Window</option>
              <option>Middle</option>
              <option>No preference</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Special Assistance</label>
            <input type="text" class="form-control" placeholder="e.g. Wheelchair for mother">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Preferred Airline(s)</label>
            <input type="text" class="form-control" placeholder="e.g. Emirates, Qantas">
          </div>
          <div class="form-group col-md-6">
            <label>Frequent Flyer No.</label>
            <input type="text" class="form-control" placeholder="e.g. QF123456">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Booking Behavior</label>
            <textarea class="form-control" rows="2" placeholder="e.g. Usually books 2 weeks ahead, price sensitive"></textarea>
          </div>
          <div class="form-group col-md-6">
            <label>Communication Preference</label>
            <select class="form-control">
              <option>Email</option>
              <option>WhatsApp</option>
              <option>Phone Call</option>
              <option>SMS</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Tags (Select all that apply)</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="tagBaggage" value="baggage">
            <label class="form-check-label" for="tagBaggage">🧳 Baggage-Conscious</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="tagFestive" value="festive">
            <label class="form-check-label" for="tagFestive">🎉 Festival Fares</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="tagFlexible" value="flexible">
            <label class="form-check-label" for="tagFlexible">📅 Date Flexibility</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="tagGroup" value="group">
            <label class="form-check-label" for="tagGroup">👪 Group Travel Potential</label>
          </div>
        </div>

        <div class="text-right mt-3">
          <button type="submit" class="btn btn-success">Save Notes</button>
          <button type="reset" class="btn btn-outline-secondary">Clear</button>
        </div>
      </form>
    </div>
  </div>
</div>

  </div>

  <!-- Modals (View, Convert, Log Call, Callback, Pax) -->
  <!-- You already have these written perfectly above -->
  <!-- For brevity, you can reuse them from your previous HTML -->

</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
        // Airline Dropdown Functionality
        const display = document.getElementById('airlineDisplay');
        const options = document.getElementById('airlineOptions');
        const hiddenInput = document.getElementById('airlineInput');
        const selectedText = document.getElementById('airlineSelected');

        // Toggle dropdown visibility
        display.addEventListener('click', (e) => {
            e.stopPropagation();
            options.style.display = (options.style.display === 'block') ? 'none' : 'block';
        });

        // Handle item selection
        document.querySelectorAll('.dropdown-option').forEach(option => {
            option.addEventListener('click', (e) => {
                const selectedCode = e.target.getAttribute('data-value');
                const selectedLabel = e.target.getAttribute('data-label');

                selectedText.textContent = selectedLabel;
                hiddenInput.value = selectedCode;
                options.style.display = 'none';
            });
        });

        // Close dropdown on outside click
        document.addEventListener('click', function() {
            options.style.display = 'none';
        });

        // Class Selection Functionality
        const serviceClassSelect = document.getElementById('serviceClass');
        const serviceClassDisplay = document.querySelector('.field-text');

        serviceClassSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex].text;
            serviceClassDisplay.textContent = selectedOption;
        });

        // Show class options when clicked
        document.querySelector('.field-holder').addEventListener('click', function(e) {
            e.stopPropagation();
            serviceClassSelect.size = serviceClassSelect.options.length;
            serviceClassSelect.style.display = 'block';
            serviceClassSelect.style.position = 'absolute';
            serviceClassSelect.style.zIndex = '1000';
            serviceClassSelect.style.width = '150px';
        });

        // Hide class options when clicked outside
        document.addEventListener('click', function() {
            serviceClassSelect.size = 1;
            serviceClassSelect.style.display = 'none';
        });

        // Toggle return date based on trip type
        const oneWayRadio = document.querySelector('input[value="One-way trip"]');
        const roundTripRadio = document.querySelector('input[value="Return trip"]');
        const returnDateInput = document.getElementById('return-date');

        oneWayRadio.addEventListener('change', function() {
            if (this.checked) {
                returnDateInput.disabled = true;
                returnDateInput.value = '';
            }
        });

        roundTripRadio.addEventListener('change', function() {
            if (this.checked) {
                returnDateInput.disabled = false;
            }
        });
    </script>
<script>
  function openNestedModal(currentModalId, nextModalId) {
    $('#' + currentModalId).modal('hide');
    $('#' + currentModalId).on('hidden.bs.modal', function () {
      setTimeout(() => {
        $('#' + nextModalId).modal('show').focus();
      }, 100);
      $(this).off('hidden.bs.modal');
    });
  }
</script>


</body>
</html>
