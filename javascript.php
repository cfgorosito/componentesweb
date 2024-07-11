render() {
    var $=this;
    $.funciones = []; $.funciones2 = [] ;
    $.qs("[conectar]").onclick = x=>{$.conectar($)};
    $.qs("[desconectar]").onclick=f=>{$.desconectar($)};
    $.qs("button[enviar]").onclick=f=>{$.grabar($)};
    $.qs("input[enviar]").onkeyup = function($tecla){
        if( /enter|return/i.test($tecla.code) ) {
            $.grabar($);  
            this.select() ;
        }
    };
    $.puerto = null ;
    $.deco = null ;
    $.lector = null ;
    $.grabador = null ;
    if( $[HATT]("auto") && 0 ) {
        navigator
            .serial
            .getPorts()
            .then(
                function( $lista ) {
                    if( !$lista ) return ;
                    $.puerto = $lista[0];
                    $.conectar();
                }
            )
    }
}

avisar($mensaje){
    var $ul = this.qs("[logs]") ;
    var $f = new Date() ;
    var h = $f.getHours();
    var m = $f.getMinutes() ;
    var s = $f.getSeconds() ;
    if( m < 10 ) m = "0"+m ;
    if( s < 10 ) s = "0"+s ;
    $ul[CODIGO] = `<option>${h}:${m}:${s} - ${$mensaje}</option>${$ul[CODIGO]}`;
}
async enviar($mensaje) {var $=this;
    if( typeof $mensaje == "string" ) {
        this.qs("input[enviar]").value = $mensaje ;
        this.grabar(this);
    } else {
        for( var $i in $mensaje ) {
            setTimeout(
                function() {
                    $.qs("input[enviar]").value = $mensaje;
                    $.grabar($)
                }
                , $i*70
            )
        }
    }
}
grabar($this) {
    $this = $this||this; //$this || this ;
    var $s = $this.shadowRoot, $=this;
    if( !$this.conectado ) return ;
    if( window.AppInventor ) {
        AppInventor.setWebViewString( 
            "enviararduino:"
            +$.qs("input[enviar]").value
            +($.qs("input[conEnter]").value=="on" ? "\n":"")
        );
        return ;
    }
    $this.grabador.write( 
        $.qs("input[enviar]").value.trim() 
        + ($.qs("input[conEnter]").value == "on" ? "\n" : "")
    ) ;
    $this.grabador.releaseLock() ;
    $this.grabador = $this.outputStream.getWriter();
}
async conectar($this) {
    $this = $this || this ;
    $this.avisar("Solicitando conexión al usuario") ;
    if( window.AppInventor ) {
        AppInventor.setWebViewString( "activararduino" ) ;
        $this.conectado = true ;
        $this.qs("input[type=checkbox]")[SATT]("checked","checked");
        $this.avisar("Leyendo");
        
        if( $this[HATT]("eventos") || $this[HATT]("onrenglon") ) {
            try{
                $this.eventos = window[$this[GATT]("eventos")] || function(n){console.log(n)};
                console.log( $this[GATT]("onrenglon") ) ;
                $this.onrenglon = window[$this[GATT]("onrenglon")] || function(n){console.log(n)};
                console.log($this.onrenglon)
            }
            catch(e){ console.log(56, e)} ;

            window.fnRecibirArduino = function( $mensaje ) {
                if( !$this.conectado ) return ;
                $this.eventos.data && typeof $this.eventos.data == "function" &&
                    $this.eventos.data( $mensaje ) ;
                $this.onrenglon && ( $mensaje ) ;
            }
        }
    }
    else
    try {
        //if( !$this[HATT]("auto") )
        $this.puerto = await navigator.serial.requestPort({
            filter: [
                  { usbVendorId: 0x2341, usbProductId: 0x0043 }
                , { usbVendorId: 0x2341, usbProductId: 0x0001 }
            ] 
        }) ;
        navigator.serial.addEventListener( "disconnect", function() {
            if( $this[HATT]("ondesconectado") ) 
                window[ $this[GATT]("ondesconectado") ]();
            if( $this.eventos && $this.eventos.desconectado && typeof $this.eventos.desconectado == "function" ) {
                $this.eventos.desconectado() ;
            }
            $this.desconectar($this) ;
        },false);
        await $this.puerto.open(
            {
                baudRate: $this[GATT]("baudios")||9600
            }
        );
        $this.deco = new TextDecoderStream();
        $this.inputDone = $this.puerto.readable.pipeTo( $this.deco.writable ) ;
        $this.inputStream = $this.deco.readable;
        $this.lector = $this.inputStream.getReader();
    
        $this.codi = new TextEncoderStream();
        $this.outputDone = $this.codi.readable.pipeTo( $this.puerto.writable ) ;
        $this.outputStream = $this.codi.writable;
    
        $this.qs("input[type=checkbox]")[SATT]("checked","checked");
        $this.avisar("Leyendo");
        $this.conectado=true ;
        if( $this[GATT]("eventos") ) {
            try{
                $this.eventos = window[$this[GATT]("eventos")] ;
                if( $this.eventos.conectado ) $this.eventos.conectado() ;
            }
            catch(e){} 
        }
        if( $this[HATT]("onconectado")) {
            try {
                window[$this[GATT]("onconectado")]() ;
            }catch(e){}
        }
        if( $this[HATT]("onrenglon") ) {
            try{
                $this.onrenglon = window[$this[GATT]("onrenglon")] ;
            }
            catch(e){} 
        }
        $this.grabador = $this.outputStream.getWriter();
        $this.readLoop();
    } catch($e){
        $this.avisar( "El usuario canceló" );
        $this.eventos = window[$this[GATT]("eventos")] ;
        if( $this.eventos.cancelado  )$this.eventos.cancelado( "Conexión cancelada" ) ;
        console.log( $e )
    }
}

async readLoop() {
    let $datos ;
    var $ = this;
    var $s = this.shadowRoot ;
    var $resul = $.qs("[resultado]");
    var renglon = "" ;
    this.habilitarProtocolo() ;
    while( true ) {
        $datos = await this.lector.read() ;
        if( $datos.value ) {
            renglon += $datos.value ;
            if( /\n/.test(renglon) ) {
                var rrenglon = renglon.split("\n") ;
                if( this.eventos && this.eventos.renglon ) this.eventos.renglon(rrenglon[0]) ;
                $resul.value = rrenglon[0] + "\n" + $resul.value ;
                this.funciones.forEach(function(fn){
                    fn( rrenglon[0] )
                });
                if( this.onrenglon ) this.onrenglon( rrenglon[0] );
                if( renglon.includes(":") ) {
                    try {
                        var prv = renglon.split(":")[0];
                        if( this.eventos && this.eventos[prv] )
                        this.eventos[prv](renglon.substr(renglon.indexOf(":")+1).trim())
                        this.funciones2.forEach(function(fn){
                            fn( prv, renglon.substr(renglon.indexOf(":")+1).trim() )
                        })
                    }catch(e){}
                }
                if( /Bolon/.test(renglon) ) {
                    [...$.children].forEach(function($hijo, $orden){
                        var $pin = $hijo[GATT]("pin") ;
                        setTimeout( function() {
                        if( $hijo.tagName == "SALIDA-DIGITAL" ) 
                            $.enviar("SALIDA("+$pin+")") ;
                        if( $hijo.tagName == "SALIDA-ANALOGICA" ) 
                            $.enviar("SALIDA(A"+$pin+")") ;
                        if( $hijo.tagName == "SERVO-MOTOR" )
                            $.enviar( "SERVO:HABILITAR("+$pin+")" );
                        if( $hijo.tagName == "ENTRADA-DIGITAL" ) 
                            $.enviar( "SUSCRIBIR("+$pin+")" );
                        if( $hijo.tagName == "ENTRADA-ANALOGICA" )
                            $.enviar( "SUSCRIBIR(A"+$pin+")" ) ;
                        }, 100*$orden);
                        
                        if( /salida|servo/i.test($hijo.tagName) )
                        $hijo.write = function($n) {
                            var $pin = this[GATT]("pin") ;
                            var $esPWM = this[HATT]("pwm") ;
                            var $esServo = this.tagName=="SERVO-MOTOR" ;
                            if( $esServo ) $.enviar( `SERVO:${$pin}?${$n}` );
                            else {
                                if( $n > 0 ) $.enviar( `PRENDER(${$pin})` ) ;
                                else $.enviar( `APAGAR(${$pin})` ) ;
                            }
                        }
                        
                    })
                    if( this.eventos && this.eventos.iniciar ) this.eventos.iniciar() ;
                    if( window.fnArduinoIniciar ) fnArduinoIniciar() ;
                    if( window.fnarduinoiniciar ) fnarduinoiniciar() ;
                    
                }
                if( /ANALOGO\((.*?)\)/.test(renglon) ) {
                    var $r = renglon.match(/ANALOGO\((.*?)\)/)[1].split(",") ;
                    if( $r && this.eventos && this.eventos.analogo ) this.eventos.analogo($r[0], $r[1]) ;
                    if( window.ANALOGO ) ANALOGO($r[0],$r[1]) ;
                    if( window.fnArduinoAnalogo ) fnArduinoAnalogo($r[0],$r[1]);
                }
                if( /DIGITAL\((.*?)\)/.test(renglon) ) {
                    var $r = renglon.match(/DIGITAL\((.*?)\)/)[1].split(",") ;
                    if(this.eventos && this.eventos.digital ) {
                        this.eventos.digital($r[0], $r[1]) ;
                    }
                    if( window.fnArduinoDigital ) fnArduinoDigital($r[0],$r[1]) ;
                    if( window.fnarduinodigital ) fnarduinodigital($r[0],$r[1]) ;
                    if( window.DIGITAL ) DIGITAL($r[0],$r[1]);
                }
                if( /CM\((.*?)\)/.test(renglon) ) {
                    if(this.eventos && this.eventos.cm ) {
                        var $r = renglon.match(/CM\((.*?)\)/)[1].split(",");
                        this.eventos.cm($r[0],$[1]) ;
                    }
                    if( window.fnArduinoCM ) fnArduinoCM($r[0],$r[1]) ;
                    if( window.fnarduinocm ) fnarduinocm($r[0],$r[1]) ;
                }
                if( /REMOTO\((.*?)\)/.test(renglon) ) {
                    var $r = renglon.match(/REMOTO\((.*?)\)/)[1].split(",");
                    if(this.eventos && this.eventos.remoto ) {
                        this.eventos.remoto($r[0],$[1]) ;
                    }
                    if( window.fnArduinoRemoto ) fnArduinoRemoto($r[0],$r[1]) ;
                    if( window.fnarduinoremoto ) fnarduinoremoto($r[0],$r[1]) ;
                }
                if( /DHT:HUM\((.*?)\)/.test(renglon) ) {
                    if($r && this.eventos && this.eventos.humedad ){
                        var $r = renglon.match( /DHT:HUM\((.*?)\)/ )[1] ;
                        this.eventos.humedad($r) ;
                    }
                    if( window.fnArduinoHumedad ) fnArduinoHumedad($r) ;
                    if( window.fnarduinohumedad ) fnarduinohumedad($r) ;
                }
                if( /DHT:(.*?)TEMP\((.*?)\)/.test(renglon) ) {
                    if($r && this.eventos && this.eventos.temperatura ){
                        var $r = renglon.match( /DHT:(.*?)TEMP\((.*?)\)/ )[2] ;
                        this.eventos.temperatura($r) ;
                    }
                    if( window.fnArduinoTemperatura ) fnArduinoTemperatura($r) ;
                    if( window.fnarduinotemperatura ) fnarduinotemperatura($r) ;
                }
                renglon = rrenglon[1]||"" ;
            }
            if( this.eventos && this.eventos.data ) this.eventos.data($datos.value);
        }
        if( $datos.done ) {
            console.log( "Lectura detenida", $datos.done ) ;
            this.shadowRoot.querySelector("input[type=checkbox]").removeAttribute("checked") ;
            if(this.eventos && this.eventos.listo) this.eventos.listo($datos.done);
            this.lector.releaseLock() ;
            break ;
        }
    }
   
}

async desconectar( $this ) {
    $this = $this || this ;
    if( !$this.conectado ) return ;
    if( window.AppInventor ) {
        AppInventor.setWebViewString( "desactivararduino" ) ;
        $this.conectado = false ;
        $this.shadowRoot.querySelector("input[type=checkbox]").removeAttribute("checked");
        $this.avisar("Desconectando");    
        return ;
    }
    if( $this.lector ) {
        await $this.lector.cancel() ;
        await $this.inputDone.catch( () => {} ) ;
        $this.lector = null ;
        $this.inputDone = null ;
    }
    if( $this.grabador ) {
        try {
            $this.grabador.close() ;
            await $this.outputDone ;
            $this.outputDone = null ;
            $this.grabador = null ;}
        catch(e){}
    }
    
    await $this.puerto.close() ;
    $this.avisar( "Desconectado" );
    $this.puerto = null ;
    $this.conectado = false ;
}

habilitarProtocolo() {
    var $ = this ;
    window.ARDUINOENVIA=[];
    window.ARDUINO = async function($$) {await $.enviar($$);};
    window.SALIDA = function(n) { ARDUINO("SALIDA("+n+")")};
    window.PRENDER = function(n) {ARDUINO("PRENDER("+n+")")};
    window.APAGAR = function(n) {ARDUINO("APAGAR("+n+")")};
    window.SERVOHABILITAR = function(n){ARDUINO("SERVO:HABILITAR("+n+")")};
    window.SERVO = function(n,ang){ARDUINO("SERVO("+n+"?"+ang+")")};
    window.SUSCRIBIR = function(n){ARDUINO("SUSCRIBIR("+n+")")}
    window.DESUSCRIBIR = function(n){ARDUINO("DESUSCRIBIR("+n+")")}
    window.A0 = 14 ;
    window.A1 = 15 ;
    window.A2 = 16 ;
    window.A3 = 17 ;
    window.A4 = 18 ;
    window.A5 = 19 ;
    window.A6 = 20 ;
    window.A7 = 21 ;

    if( $[HATT]("salidas") ) {
        var split = $[GATT]("salidas").split(" ") ;
        split.forEach(x=>ARDUINO("SALIDA("+x+")"));
        
    }
}


suscribir(fn,segundo) {
    if( segundo ) {
        this.funciones2.push(function($evento,$valor){
            if( $evento == fn ) segundo( $valor, $evento )
        })
    } else
    this.funciones.push(fn)
}

eliminar() {
    this.funciones = [] ;
    this.funciones2 = [];
}
