<?php

class Nzymes_Stack {
    protected $stack = array();

    public
    function push() {
        $items = func_get_args();
        array_splice( $this->stack, count( $this->stack ), 0, $items );
        $result = count( $this->stack );

        return $result;
    }

    public
    function pop( $last = 1 ) {
        if ( empty( $this->stack ) ) {
            return null;
        }
        $last   = max( array( $last, 1 ) );
        $result = array_splice( $this->stack, - $last );

        return $result;
    }

    public
    function peek( $last = 1 ) {
        if ( empty( $this->stack ) ) {
            return null;
        }
        $last   = max( array( $last, 1 ) );
        $result = array_slice( $this->stack, - $last );

        return $result;
    }
}
