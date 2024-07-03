<?php
// Ultimate Member - Directory Private Fields Version 1.0.0

    $template =
                '<div  id="um_field_0_hide_in_members" class="um-field um-field-radio  um-field-hide_in_members um-field-radio um-field-type_radio" data-key="hide_in_members" aria-invalid="false" >

                    <div class="um-field-label">
                        <label for="hide_in_members">Hide my profile\'s "%s" field from directory</label>
                        <span class="um-tip um-tip-w" title="Here you can hide yourself from appearing in public directory with this profile field">
                            <i class="um-icon-help-circled"></i>
                        </span>
                        <div class="um-clear"></div>
                    </div>

                    <div class="um-field-area">
                        <label class="um-field-radio active um-field-half ">
                            <input type="radio" name="hide_meta_key[]" value="no" no_checked />
                            <span class="um-field-radio-state">
                                <i class="um-icon-android-radio_button_no"></i>
                            </span>
                            <span class="um-field-radio-option">No</span>
                        </label>

                        <label class="um-field-radio  um-field-half  right ">
                            <input type="radio" name="hide_meta_key[]" value="yes" yes_checked />
                            <span class="um-field-radio-state">
                                <i class="um-icon-android-radio_button_yes"></i>
                            </span>
                            <span class="um-field-radio-option">Yes</span>
                        </label>

                        <div class="um-clear"></div>
                    </div>
                </div>';

    $updates = array(
        'Here you can hide yourself from appearing in public directory with this profile field',
        'No',
        'Yes',
    );

    $replaces = array(
        __( 'Here you can hide yourself from appearing in public directory with this profile field', 'directory_private_fields'),
        __( 'No', 'directory_private_fields'),
        __( 'Yes', 'directory_private_fields'),
    );

    $template = str_replace( $updates, $replaces, $template );
