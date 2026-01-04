import{r as x,c as S,u as A,H as I,I as M,a as h,o as g,b,d as f,h as V,e as y,f as e,F as C,n as D,i as F,t as n,m as O,p as L,g as B,j as U,x as H,l as R}from"./app-DKC0vr7_.js";import{_ as W}from"./AuthenticatedLayout-DEUhirSK.js";import{_ as G}from"./AlertModal-B9m-Qoht.js";import"./Modal-CrVCiEXj.js";const Q={class:"flex items-center space-x-4"},Y={class:"font-semibold text-xl text-gray-800 leading-tight"},J={class:"py-12"},K={class:"max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-8"},X={class:"lg:col-span-2 space-y-8"},Z={class:"bg-white overflow-hidden shadow-sm sm:rounded-lg"},ee={class:"p-6"},te=e("h3",{class:"text-lg font-semibold border-b pb-4 mb-4"},"Productos en la Orden",-1),oe={class:"divide-y divide-gray-200"},se={class:"flex-grow"},ae={class:"font-bold text-gray-900"},ne={key:0,class:"text-sm text-gray-600 mt-1"},re={class:"font-semibold"},de={class:"text-sm text-gray-500 mt-2"},le={class:"font-semibold text-gray-800 shrink-0 ml-4"},ie={class:"border-t border-gray-200 pt-4 mt-4 text-right"},ce={class:"text-2xl font-bold"},ue={class:"bg-white overflow-hidden shadow-sm sm:rounded-lg"},me={class:"p-6"},pe=e("h3",{class:"text-lg font-semibold border-b pb-4 mb-4"},"Datos del Cliente",-1),he={class:"mt-4 space-y-4 text-gray-700"},ge=e("p",{class:"font-semibold text-sm text-gray-500"},"Nombre:",-1),_e=e("p",{class:"font-semibold text-sm text-gray-500"},"Teléfono:",-1),ve=e("p",{class:"font-semibold text-sm text-gray-500"},"Correo:",-1),be=e("p",{class:"font-semibold text-sm text-gray-500"},"Dirección:",-1),fe={class:"space-y-8"},xe={class:"bg-white overflow-hidden shadow-sm sm:rounded-lg"},ye={class:"p-6"},$e=e("h3",{class:"text-lg font-semibold mb-4"},"Estado del Pedido",-1),we={class:"text-center mb-4"},Se={class:"text-sm text-center text-gray-500"},Ce={class:"mt-6 border-t pt-6"},Ne=e("label",{for:"status",class:"block font-medium text-sm text-gray-700 mb-2"},"Cambiar Estado",-1),Ee=e("option",{value:"recibido"},"Recibido",-1),Te=e("option",{value:"en_preparacion"},"En Preparación",-1),De=e("option",{value:"despachado"},"Despachado",-1),Oe=e("option",{value:"entregado"},"Entregado",-1),je=e("option",{value:"cancelado"},"Cancelado",-1),ke=[Ee,Te,De,Oe,je],qe=["disabled"],Pe=e("div",{class:"p-6"},[e("div",{class:"mt-6 flex justify-end"})],-1),Ve={__name:"Show",props:{order:Object},setup(i){const u=x(!1),m=x(""),p=x(""),_=x("success"),a=i,N=t=>{const o={year:"numeric",month:"long",day:"numeric",hour:"2-digit",minute:"2-digit"};return new Date(t).toLocaleDateString("es-CO",o)},$=S(()=>({recibido:{text:"Recibido",class:"bg-yellow-100 text-yellow-800"},en_preparacion:{text:"En Preparación",class:"bg-blue-100 text-blue-800"},despachado:{text:"Despachado",class:"bg-purple-100 text-purple-800"},entregado:{text:"Entregado",class:"bg-green-100 text-green-800"},cancelado:{text:"Cancelado",class:"bg-red-100 text-red-800"}})[a.order.status]||{text:a.order.status,class:"bg-gray-100 text-gray-800"}),v=A({status:a.order.status}),j=()=>{const t=v.status;v.put(route("admin.orders.update",a.order.id),{preserveScroll:!0,onSuccess:()=>{_.value="success",t==="despachado"||t==="entregado"?(m.value="Venta confirmada",p.value="Inventario actualizado correctamente."):t==="cancelado"?(m.value="Pedido cancelado",p.value="El estado se actualizó y el inventario fue revertido si correspondía."):(m.value="Estado actualizado",p.value="El estado de la orden se guardó correctamente."),u.value=!0},onError:o=>{_.value="error",m.value="Error al actualizar",p.value=Array.isArray(o)?o.join(`
`):o?.status||Object.values(o||{}).join(`
`)||"Ocurrió un error inesperado.",u.value=!0}})},E=I(),w=S(()=>E?.props?.auth?.user?.store?.name||"nuestra tienda"),T=S(()=>{const t=E?.props?.auth?.user?.store;return t?t.custom_domain?`${typeof window<"u"?window.location.protocol:"https:"}//${t.custom_domain}`:route("catalogo.index",{store:t.slug}):"#"}),k=()=>{try{const t=Array.isArray(a.order?.items)?a.order.items:[];return t.length?`
Tu orden incluye:
${t.map(r=>{const s=Number(r.quantity||0);let d="";if(r.variant_options)try{const l=Object.entries(r.variant_options).map(([c,z])=>`${c}: ${z}`);d=l.length?` (${l.join(", ")})`:""}catch{}return` - ${s} x ${r.product_name}${d}`}).join(`
`)}`:""}catch{return""}},q=()=>{const t=a.order.sequence_number??a.order.id,o=N(a.order.created_at),r=`$ ${Number(a.order.total_price).toLocaleString("es-CO")}`,s=(a.order?.customer_name||"").trim(),d=s?`Hola ${s},`:"Hola,",l=(a.order?.customer_address||"").trim(),c=k();switch(a.order.status){case"recibido":return`${d}

¡Gracias por comprar en ${w.value}!

Tu pedido No. ${t} fue recibido correctamente.${c?`
${c}`:""}

Fecha del pedido: ${o}.
Total del pedido: ${r}${l?`
Dirección: ${l}`:""}

Te avisaremos cuando esté en preparación.`;case"en_preparacion":return`${d}

Estamos preparando tu pedido No. ${t} en ${w.value}.${c?`
${c}`:""}

Fecha del pedido: ${o}.
Total del pedido: ${r}${l?`
Dirección: ${l}`:""}

Te notificaremos cuando salga a despacho.`;case"despachado":return`${d}

Tu pedido No. ${t} fue despachado desde ${w.value}.${c?`
${c}`:""}

Total del pedido: ${r}${l?`
Dirección de entrega: ${l}`:""}

Si necesitas coordinar algo de la entrega, responde este mensaje.`;case"entregado":return`${d}

¡Qué alegría! Ya entregamos tu pedido No. ${t}.${c?`
${c}`:""}

Total del pedido: ${r}

Esperamos que lo disfrutes. Si quieres descubrir más productos, visita nuestro catálogo: ${T.value}`;case"cancelado":return`${d}

Tu pedido No. ${t} fue cancelado. Si no fuiste tú quien lo solicitó, por favor contáctanos para ayudarte.${c?`
${c}`:""}

Cuando desees, puedes volver a comprar aquí: ${T.value}`;default:return`${d}

Estado actual de tu pedido No. ${t}: ${$.value?.text??a.order.status}.`}},P=()=>{try{const o=(a.order?.customer_phone??"").toString().replace(/[^0-9]/g,"");if(!o){_.value="error",m.value="Sin teléfono del cliente",p.value="No encontramos un número de WhatsApp válido para el cliente.",u.value=!0;return}const r=q(),s=encodeURIComponent(r),d=`https://wa.me/${o}?text=${s}`;typeof window<"u"&&window.open(d,"_blank")}catch{_.value="error",m.value="No se pudo abrir WhatsApp",p.value="Intenta nuevamente en unos segundos.",u.value=!0}};return(t,o)=>{const r=M("Modal");return g(),h(C,null,[b(f(V),{title:`Orden #${i.order.sequence_number??i.order.id}`},null,8,["title"]),b(W,null,{header:y(()=>[e("div",Q,[b(f(R),{href:t.route("admin.orders.index"),class:"text-blue-600 hover:text-blue-800"},{default:y(()=>[O(" ← Volver a Órdenes ")]),_:1},8,["href"]),e("h2",Y," Detalle de la Orden #"+n(i.order.sequence_number??i.order.id),1)])]),default:y(()=>[e("div",J,[e("div",K,[e("div",X,[e("div",Z,[e("div",ee,[te,e("div",oe,[(g(!0),h(C,null,D(i.order.items,s=>(g(),h("div",{key:s.id,class:"py-4 flex justify-between items-start"},[e("div",se,[e("p",ae,n(s.product_name),1),s.variant_options?(g(),h("div",ne,[(g(!0),h(C,null,D(s.variant_options,(d,l)=>(g(),h("span",{key:l,class:"mr-2 bg-gray-100 px-2 py-1 rounded"},[e("span",re,n(l)+":",1),O(" "+n(d),1)]))),128))])):F("",!0),e("p",de,n(s.quantity)+" x $ "+n(Number(s.unit_price).toLocaleString("es-CO")),1)]),e("p",le,"$ "+n(Number(s.unit_price*s.quantity).toLocaleString("es-CO")),1)]))),128))]),e("div",ie,[e("p",ce,"Total: $ "+n(Number(i.order.total_price).toLocaleString("es-CO")),1)])])]),e("div",ue,[e("div",me,[pe,e("div",he,[e("div",null,[ge,e("p",null,n(i.order.customer_name),1)]),e("div",null,[_e,e("p",null,n(i.order.customer_phone),1)]),e("div",null,[ve,e("p",null,n(i.order.customer_email),1)]),e("div",null,[be,e("p",null,n(i.order.customer_address),1)])])])])]),e("div",fe,[e("div",xe,[e("div",ye,[$e,e("div",we,[e("span",{class:L(["px-4 py-2 inline-flex text-lg leading-5 font-semibold rounded-full",$.value.class])},n($.value.text),3)]),e("p",Se," Pedido realizado el: "+n(N(i.order.created_at)),1),e("form",{onSubmit:B(j,["prevent"])},[e("div",Ce,[Ne,U(e("select",{id:"status","onUpdate:modelValue":o[0]||(o[0]=s=>f(v).status=s),class:"block w-full rounded-md shadow-sm border-gray-300"},ke,512),[[H,f(v).status]]),e("button",{type:"submit",disabled:f(v).processing,class:"mt-4 w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 disabled:opacity-50"}," Actualizar Estado ",8,qe),e("button",{type:"button",onClick:P,class:"mt-3 w-full bg-green-600 text-white font-bold py-2 px-4 rounded hover:bg-green-700"}," Notificar estado actual ")])],32)])])])])])]),_:1}),b(r,{show:t.confirmingSale,onClose:t.closeSaleModal},{default:y(()=>[Pe]),_:1},8,["show","onClose"]),b(G,{show:u.value,type:_.value,title:m.value,message:p.value,"primary-text":"Entendido",onPrimary:o[1]||(o[1]=s=>u.value=!1),onClose:o[2]||(o[2]=s=>u.value=!1)},null,8,["show","type","title","message"])],64)}}};export{Ve as default};
