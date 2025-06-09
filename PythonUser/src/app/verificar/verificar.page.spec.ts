import { ComponentFixture, TestBed } from '@angular/core/testing';
import { VerificarPage } from './verificar.page';

describe('VerificarPage', () => {
  let component: VerificarPage;
  let fixture: ComponentFixture<VerificarPage>;

  beforeEach(() => {
    fixture = TestBed.createComponent(VerificarPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
