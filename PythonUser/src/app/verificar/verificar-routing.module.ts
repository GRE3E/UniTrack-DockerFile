import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';

import { VerificarPage } from './verificar.page';

const routes: Routes = [
  {
    path: '',
    component: VerificarPage
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class VerificarPageRoutingModule {}
