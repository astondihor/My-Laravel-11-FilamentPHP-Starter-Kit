function m({state:r,statePath:s,placeholder:o,aceUrl:a,extensions:n,config:h={},options:c={},darkTheme:l,disableDarkTheme:d}){return{state:r,statePath:s,placeholder:o,options:c,darkTheme:l,disableDarkTheme:d,editor:null,observer:null,async init(){if(!await this.importAceEditor(a)){console.error("Failed to load the ACE editor core.");return}await this.importExtensions(n)||console.error("Failed to load ACE editor extensions."),this.configureAce(h),this.initializeEditor(),this.applyInitialTheme(),this.observeDarkModeChanges()},async importAceEditor(t){try{return await import(t),!0}catch(e){return console.error("Error importing the ACE editor core:",e),!1}},async importExtensions(t){try{let e=Object.values(t).map(i=>import(i));return await Promise.all(e),!0}catch(e){return console.error("Error importing ACE editor extensions:",e),!1}},configureAce(t){Object.entries(t).forEach(([e,i])=>ace.config.set(e,i))},initializeEditor(){this.editor=ace.edit(this.$refs.aceCodeEditor),this.editor.setOptions(this.options),this.editor.session.setValue(this.state?this.state:this.placeholder),this.editor.session.on("change",()=>{this.state=this.editor.getValue()})},applyInitialTheme(){this.disableDarkTheme?this.editor.setTheme(this.options.theme):this.setTheme()},observeDarkModeChanges(){if(this.disableDarkTheme)return;let t=document.querySelector("html");this.observer=new MutationObserver(()=>this.setTheme()),this.observer.observe(t,{attributes:!0,attributeFilter:["class"]})},setTheme(){let e=document.querySelector("html").classList.contains("dark")?this.darkTheme:this.options.theme;this.editor&&this.editor.setTheme(e)}}}export{m as default};