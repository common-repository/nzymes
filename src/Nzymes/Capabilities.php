<?php

class Nzymes_Capabilities {
    const PREFIX = '__nzymes__';

//@formatter:off
    const inject                       = '__nzymes__inject';                        // It allows a user to inject enzymes into her posts.
    const use_own_attributes           = '__nzymes__use_own_attributes';            // It allows a user to make her enzymes with her own attributes.
    const use_others_attributes        = '__nzymes__use_others_attributes';         // It allows a user to make her enzymes with others\' attributes.
    const use_own_custom_fields        = '__nzymes__use_own_custom_fields';         // It allows a user to make her enzymes with her own custom fields.
    const use_others_custom_fields     = '__nzymes__use_others_custom_fields';      // It allows a user to make her enzymes with others\' custom fields.
    const create_static_custom_fields  = '__nzymes__create_static_custom_fields';   // It allows a user to create enzymes from non-evaluated custom fields.
    const create_dynamic_custom_fields = '__nzymes__create_dynamic_custom_fields';  // It allows a user to create enzymes from evaluated custom fields.
    const share_static_custom_fields   = '__nzymes__share_static_custom_fields';    // It allows a user to share her enzymes from non-evaluated custom fields.
    const share_dynamic_custom_fields  = '__nzymes__share_dynamic_custom_fields';   // It allows a user to share her enzymes from evaluated custom fields.

    const User           = '__nzymes__User';            // inject + use_own_attributes + use_own_custom_fields + create_static_custom_fields
    const PrivilegedUser = '__nzymes__PrivilegedUser';  // + use_others_custom_fields
    const TrustedUser    = '__nzymes__TrustedUser';     // + share_static_custom_fields
    const Coder          = '__nzymes__Coder';           // + create_dynamic_custom_fields
    const TrustedCoder   = '__nzymes__TrustedCoder';    // + share_dynamic_custom_fields
//@formatter:on

    /**
     * Indexed array of all the capabilities.
     *
     * @return array
     */
    static public
    function all() {
        $result = array(
            self::inject,
            self::use_own_attributes,
            self::use_others_attributes,
            self::use_own_custom_fields,
            self::use_others_custom_fields,
            self::create_static_custom_fields,
            self::create_dynamic_custom_fields,
            self::share_static_custom_fields,
            self::share_dynamic_custom_fields,
        );

        return $result;
    }

    /**
     * Indexed array of all the roles.
     *
     * @return array
     */
    static public
    function all_roles() {
        $result = array(
            self::User,
            self::PrivilegedUser,
            self::TrustedUser,
            self::Coder,
            self::TrustedCoder,
        );

        return $result;
    }

    /**
     * Associative array of the capabilities for an Nzymes User.
     *
     * @return array
     */
    public static
    function for_User() {
        $result = array_merge( array_fill_keys( self::all(), false ), array(
            self::inject                      => true,
            self::use_own_attributes          => true,
            self::use_own_custom_fields       => true,
            self::create_static_custom_fields => true,
        ) );

        return $result;
    }

    /**
     * Associative array of the capabilities for an Nzymes PrivilegedUser.
     *
     * @return array
     */
    public static
    function for_PrivilegedUser() {
        $result = array_merge( self::for_User(), array(
            self::use_others_custom_fields => true,
        ) );

        return $result;
    }

    /**
     * Associative array of the capabilities for an Nzymes TrustedUser.
     *
     * @return array
     */
    public static
    function for_TrustedUser() {
        $result = array_merge( self::for_PrivilegedUser(), array(
            self::share_static_custom_fields => true,
        ) );

        return $result;
    }

    /**
     * Associative array of the capabilities for an Nzymes Coder.
     *
     * @return array
     */
    public static
    function for_Coder() {
        $result = array_merge( self::for_TrustedUser(), array(
            self::create_dynamic_custom_fields => true,
        ) );

        return $result;
    }

    /**
     * Associative array of the capabilities for an Nzymes TrustedCoder.
     *
     * @return array
     */
    public static
    function for_TrustedCoder() {
        $result = array_merge( self::for_Coder(), array(
            self::share_dynamic_custom_fields => true,
        ) );

        return $result;
    }

}
