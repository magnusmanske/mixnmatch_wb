'use strict';

let router ;
let app ;
let wd = new WikiData() ;
wd.api = 'https://mixnmatch.wmflabs.org/w/api.php?callback=?' ;
wd.sparql_url = 'https://mixnmatch-query.wmflabs.org/sparql' ;

$(document).ready ( function () {
    vue_components.toolname = "mix-n-match" ;
    vue_components.components_base_url = 'https://mixnmatch.wmflabs.org/interface/resources/vue/' ;
    Promise.all ( [
            vue_components.loadComponents ( ['widar','wd-date','wd-link','tool-translate','tool-navbar','commons-thumbnail','snak','value-validator','typeahead-search',
                'vue_components/main-page.html',
                ] ) ,
            new Promise(function(resolve, reject) {
                resolve() ;
            } )
    ] ) .then ( () => {
        wd_link_wd = wd ;

        const routes = [
            { path: '/', component: MainPage , props:true },
//            { path: '/tab', component: TablePage , props:true },
//            { path: '/tab/:mode/:main_init', component: TablePage , props:true },
//            { path: '/tab/:mode/:main_init/:cols_init', component: TablePage , props:true },
        ] ;
        router = new VueRouter({routes}) ;
        app = new Vue ( { router } ) .$mount('#app') ;
    } ) ;
} ) ;
