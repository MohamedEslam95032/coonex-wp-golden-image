const m=t=>{if(!t)return"";const e=new Date(t),n={month:"short",day:"numeric"},o={hour:"numeric",minute:"2-digit",hour12:!0},r=new Intl.DateTimeFormat(void 0,n).format(e),a=new Intl.DateTimeFormat(void 0,o).format(e);return`${r}, ${a}`};export{m as f};
//# sourceMappingURL=formatDateString-CpJBBOOJ.js.map
