'use strict';

let router ;
let app ;
let wd = new WikiData() ; // Loocal WikiBase
wd.api = 'https://mixnmatch.wmflabs.org/w/api.php?callback=?' ;
wd.sparql_url = './api.php' ;
let wdwd = new WikiData() ; // Wikidata

let global_configuration , _props , _items ;

$(document).ready ( function () {
    vue_components.toolname = "mix-n-match" ;
    vue_components.components_base_url = 'https://mixnmatch.wmflabs.org/interface/resources/vue/' ;
    Promise.all ( [
            vue_components.loadComponents ( ['widar','wd-date','wd-link','tool-translate','tool-navbar','commons-thumbnail','snak','batch-navigator','value-validator','typeahead-search',
                'vue_components/mixin-edit.html',
                'vue_components/sparql-results.html',
                'vue_components/entry.html',
                'vue_components/catalog-dropdown.html',
                'vue_components/catalog-header.html',
                'vue_components/main-page.html',
                'vue_components/catalog-page.html',
                'vue_components/recent-changes.html',
                'vue_components/list-page.html',
                'vue_components/entry-page.html',
                ] ) ,
            new Promise(function(resolve, reject) {
                $.get ( './config.json' , function ( d ) {
//                    console.log(JSON.parse(JSON.stringify(d))) ;
                    global_configuration = d ;
                    _props = d.props ;
                    _items = d.items ;
                    resolve() ;
                } , 'json' ) ;
            } )
    ] ) .then ( () => {
        wd_link_wd = wd ;

        const routes = [
            { path: '/', component: MainPage , props:true },
            { path: '/entry/:q', component: EntryPage , props:true },
            { path: '/catalog/:catalog_q', component: CatalogPage , props:true },
            { path: '/rc/:catalog_q', component: RecentChangesPage , props:true },
            { path: '/list/:mode/:catalog_q', component: ListPage , props:true },
            { path: '/list/:mode/:catalog_q/:page', component: ListPage , props:true },
        ] ;
        router = new VueRouter({routes}) ;
        app = new Vue ( { router } ) .$mount('#app') ;
    } ) ;
} ) ;
